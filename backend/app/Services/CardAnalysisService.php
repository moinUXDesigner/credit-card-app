<?php

namespace App\Services;

use App\Exceptions\CardAnalysisUnavailableException;
use App\Models\Card;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class CardAnalysisService
{
    /**
     * Analyze an uploaded statement PDF or card photo via the OpenAI Responses
     * API and return prefill data for the Add Card form. The file is only
     * ever read into memory (base64) for this one request — never persisted.
     */
    public function analyze(UploadedFile $file): array
    {
        $mediaType = $file->getMimeType();
        $data = base64_encode(file_get_contents($file->getRealPath()));

        $fileBlock = $mediaType === 'application/pdf'
            ? ['type' => 'input_file', 'filename' => $file->getClientOriginalName(), 'file_data' => "data:{$mediaType};base64,{$data}"]
            : ['type' => 'input_image', 'image_url' => "data:{$mediaType};base64,{$data}"];

        $params = [
            'model' => config('services.openai.model'),
            'input' => [
                ['role' => 'user', 'content' => [
                    ['type' => 'input_text', 'text' => $this->buildPrompt()],
                    $fileBlock,
                ]],
            ],
            'tools' => [['type' => 'web_search']],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'card_analysis',
                    'schema' => $this->buildSchema(),
                    'strict' => true,
                ],
            ],
        ];

        try {
            $response = $this->createResponse($params);
        } catch (\Throwable $e) {
            report($e);

            if ($mediaType === 'application/pdf') {
                return app(StatementTextExtractionService::class)->extract($file);
            }

            throw new CardAnalysisUnavailableException(
                'The AI analysis service is temporarily unavailable. Please fill in the details manually.'
            );
        }

        if (($response['status'] ?? null) === 'failed') {
            throw new CardAnalysisUnavailableException(
                "We couldn't analyze this file. Please fill in the details manually."
            );
        }

        $json = $this->firstOutputTextJson($response);

        if ($json === null) {
            throw new CardAnalysisUnavailableException(
                "We couldn't analyze this file. Please fill in the details manually."
            );
        }

        return $this->sanitize($json);
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

    private function buildPrompt(): string
    {
        return <<<'PROMPT'
            You are extracting fields from a credit card statement or a photo of a physical
            credit card, to prefill a form. Only fill in fields you can read with confidence;
            use null for anything unclear or not present — never guess a value.

            If the file is not a recognizable credit card statement or a photo of a credit
            card, set document_recognized to false and confidence to "none", leave every
            extractable field null, and return an empty suggested_benefits array.

            Step 1 — read the document itself. Statements often print a rewards/benefits
            summary section, and card mailers/welcome kits sometimes print welcome-offer or
            benefit text directly. Extract any reward point balance, reward multiplier,
            lounge visit counts, cashback earned, or named benefits/offers you can actually
            read on the page, independent of any web search.

            Step 2 — as soon as you can read a bank name at all (even if you're not fully
            certain of the exact card product), use the web_search tool to find that card's
            official benefits/features page on the issuing bank's website. Search using
            whatever combination of bank name, partial product name, network, and visible
            category hints you have — do not skip this step just because the exact product
            name is uncertain; only skip it if no bank can be identified at all. Use the
            search results (and the fee schedule/MITC document if you find one) to fill:
            best_categories (only values from the fixed list in the schema), reward_rate_general,
            lounge_access, cashback_cap_amount, forex_markup_percent, fuel_surcharge_waiver_percent,
            insurance_cover_amount, waiver_spend_required, and suggested_benefits.

            waiver_spend_required is the total amount the cardholder must spend within the fee
            year for the annual fee to be waived — look for wording like "annual fee waived on
            spends of ₹X". forex_markup_percent is the foreign currency transaction markup fee.
            fuel_surcharge_waiver_percent is the fuel surcharge waiver percentage (not the
            surcharge itself — the portion waived back). insurance_cover_amount is the total
            cover amount of any complimentary purchase protection, travel, or accident
            insurance bundled with the card.

            For suggested_benefits, list every distinct benefit you find as its own array
            entry rather than merging them into one summary — for example: the joining/welcome
            bonus, each milestone spend bonus separately if there are several thresholds,
            domestic lounge access and international lounge access as separate entries when
            both exist with different visit counts, dining/movie ticket offers, renewal-fee
            reversal offers, and any other named program not already captured by a discrete
            field above. Use the existing type enum for each (lounge/cashback/reward_points/
            dining/movie/other) — use "other" for anything that doesn't fit cleanly.

            If you cannot identify a specific product, the search finds nothing useful, or a
            particular fact is genuinely not published for this card, leave that field null or
            empty rather than guessing — thoroughness never overrides the "never guess" rule.
            PROMPT;
    }

    private function buildSchema(): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $nullableNumber = ['type' => ['number', 'null']];
        $nullableInteger = ['type' => ['integer', 'null']];
        $nullableBoolean = ['type' => ['boolean', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'document_recognized', 'confidence', 'card_name', 'bank_name', 'last_four_digits',
                'network', 'total_limit', 'statement_day', 'due_day', 'annual_fee_amount',
                'annual_fee_month', 'waiver_spend_required', 'reward_point_balance', 'reward_rate_general',
                'cashback_cap_amount', 'forex_markup_percent', 'fuel_surcharge_waiver_percent',
                'insurance_cover_amount', 'lounge_access', 'best_categories', 'suggested_benefits',
            ],
            'properties' => [
                'document_recognized' => ['type' => 'boolean'],
                'confidence' => ['type' => 'string', 'enum' => ['high', 'medium', 'low', 'none']],
                'card_name' => $nullableString,
                'bank_name' => $nullableString,
                'last_four_digits' => $nullableString,
                'network' => ['type' => ['string', 'null'], 'enum' => [...CardFieldSanitizer::VALID_NETWORKS, null]],
                'total_limit' => $nullableNumber,
                'statement_day' => $nullableInteger,
                'due_day' => $nullableInteger,
                'annual_fee_amount' => $nullableNumber,
                'annual_fee_month' => $nullableInteger,
                'waiver_spend_required' => $nullableNumber,
                'reward_point_balance' => $nullableNumber,
                'reward_rate_general' => $nullableNumber,
                'cashback_cap_amount' => $nullableNumber,
                'forex_markup_percent' => $nullableNumber,
                'fuel_surcharge_waiver_percent' => $nullableNumber,
                'insurance_cover_amount' => $nullableNumber,
                'lounge_access' => $nullableBoolean,
                'best_categories' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => Card::CATEGORIES]],
                'suggested_benefits' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['type', 'title', 'frequency', 'total_allowed', 'expiry_date', 'estimated_value'],
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => CardFieldSanitizer::VALID_BENEFIT_TYPES],
                            'title' => ['type' => 'string'],
                            'frequency' => ['type' => 'string', 'enum' => CardFieldSanitizer::VALID_BENEFIT_FREQUENCIES],
                            'total_allowed' => ['type' => 'integer'],
                            'expiry_date' => $nullableString,
                            'estimated_value' => $nullableNumber,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function sanitize(array $data): array
    {
        return [
            'analyzed' => true,
            'document_recognized' => (bool) ($data['document_recognized'] ?? false),
            'confidence' => $data['confidence'] ?? 'none',
            'message' => ($data['document_recognized'] ?? false)
                ? null
                : "We couldn't recognize a credit card statement or card in this file. Please fill in the details manually.",
            'card' => app(CardFieldSanitizer::class)->sanitizeCard($data),
            'suggested_benefits' => app(CardFieldSanitizer::class)->sanitizeSuggestedBenefits($data['suggested_benefits'] ?? []),
        ];
    }
}
