<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class StatementAnalysisService
{
    private const MAX_TRANSACTIONS = 200;

    /**
     * Analyze a stored statement PDF via the OpenAI Responses API and
     * extract the account summary (dates/totals) plus every transaction
     * line item, categorized against Card::CATEGORIES. No web_search here
     * — this is pure document reading, not a benefits lookup.
     *
     * @return array{
     *   analyzed: bool,
     *   message: ?string,
     *   statement: array,
     *   transactions: array<int, array>,
     * }
     */
    public function analyze(UploadedFile $file): array
    {
        $data = base64_encode(file_get_contents($file->getRealPath()));

        $params = [
            'model' => config('services.openai.model'),
            'input' => [
                ['role' => 'user', 'content' => [
                    ['type' => 'input_text', 'text' => $this->buildPrompt()],
                    [
                        'type' => 'input_file',
                        'filename' => $file->getClientOriginalName(),
                        'file_data' => "data:application/pdf;base64,{$data}",
                    ],
                ]],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'statement_analysis',
                    'schema' => $this->buildSchema(),
                    'strict' => true,
                ],
            ],
        ];

        try {
            $response = $this->createResponse($params);
        } catch (\Throwable $e) {
            report($e);

            return [
                'analyzed' => false,
                'message' => 'The AI analysis service is temporarily unavailable. The statement was saved, but no data was extracted.',
                'statement' => [],
                'transactions' => [],
            ];
        }

        if (($response['status'] ?? null) === 'failed') {
            return [
                'analyzed' => false,
                'message' => "We couldn't analyze this statement.",
                'statement' => [],
                'transactions' => [],
            ];
        }

        $json = $this->firstOutputTextJson($response);

        if ($json === null) {
            return [
                'analyzed' => false,
                'message' => "We couldn't analyze this statement.",
                'statement' => [],
                'transactions' => [],
            ];
        }

        return [
            'analyzed' => true,
            'message' => null,
            'statement' => [
                'statement_date' => $json['statement_date'] ?? null,
                'due_date' => $json['due_date'] ?? null,
                'total_due' => $json['total_due'] ?? null,
                'minimum_due' => $json['minimum_due'] ?? null,
                'credit_limit' => $json['credit_limit'] ?? null,
                'reward_point_balance' => $json['reward_point_balance'] ?? null,
            ],
            'transactions' => $this->sanitizeTransactions($json['transactions'] ?? []),
        ];
    }

    /**
     * Isolated so tests can override this single call site (e.g. via an
     * anonymous subclass) to simulate an AI failure, without having to fake
     * the outbound HTTP call for every test.
     */
    protected function createResponse(array $params): array
    {
        $response = Http::withToken(config('services.openai.api_key'))
            ->timeout(120)
            ->post('https://api.openai.com/v1/responses', $params);

        if ($response->failed()) {
            throw new \RuntimeException('OpenAI API request failed: '.$response->body());
        }

        return $response->json();
    }

    private function firstOutputTextJson(array $response): ?array
    {
        foreach ($response['output'] ?? [] as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ($item['content'] ?? [] as $block) {
                if (($block['type'] ?? null) === 'output_text') {
                    $decoded = json_decode($block['text'], true);

                    return is_array($decoded) ? $decoded : null;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, array{date: ?string, description: string, amount: float, category: ?string}>
     */
    private function sanitizeTransactions(array $transactions): array
    {
        return array_values(array_filter(array_map(function ($t) {
            if (! is_array($t) || empty($t['description']) || ! is_numeric($t['amount'] ?? null)) {
                return null;
            }

            $category = $t['category'] ?? null;

            return [
                'date' => $t['date'] ?? null,
                'description' => (string) $t['description'],
                'amount' => (float) $t['amount'],
                'category' => in_array($category, Card::CATEGORIES, true) ? $category : null,
            ];
        }, $transactions)));
    }

    private function buildPrompt(): string
    {
        return <<<'PROMPT'
            You are extracting data from a credit card statement PDF to update a spend-tracking
            app. Only fill in fields you can read with confidence; use null for anything unclear.

            First, read the account summary section for: statement_date, due_date, total_due
            (the total amount due/outstanding balance), minimum_due, credit_limit, and
            reward_point_balance (current reward points balance, if shown).

            Then read the full transaction table and list every purchase/charge line item (skip
            payments/credits received) as a separate entry: date, description (merchant/narration
            text as printed), amount, and category. category must be one of the fixed list
            provided in the schema (fuel, grocery, amazon, dining, travel, utilities, online,
            medicines, online_food, other) — pick the closest sensible match from the merchant
            name/description, or "other" if genuinely unclear. Never invent a transaction that
            isn't printed on the statement. If the statement has more transactions than fit,
            include as many as you can, prioritizing completeness over anything else.
            PROMPT;
    }

    private function buildSchema(): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $nullableNumber = ['type' => ['number', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'statement_date', 'due_date', 'total_due', 'minimum_due',
                'credit_limit', 'reward_point_balance', 'transactions',
            ],
            'properties' => [
                'statement_date' => $nullableString,
                'due_date' => $nullableString,
                'total_due' => $nullableNumber,
                'minimum_due' => $nullableNumber,
                'credit_limit' => $nullableNumber,
                'reward_point_balance' => $nullableNumber,
                'transactions' => [
                    'type' => 'array',
                    'maxItems' => self::MAX_TRANSACTIONS,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['date', 'description', 'amount', 'category'],
                        'properties' => [
                            'date' => $nullableString,
                            'description' => ['type' => 'string'],
                            'amount' => ['type' => 'number'],
                            'category' => ['type' => ['string', 'null'], 'enum' => [...Card::CATEGORIES, null]],
                        ],
                    ],
                ],
            ],
        ];
    }
}
