<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpenAiResponsesClient
{
    public function create(array $parameters): array
    {
        if (! config('services.openai.api_key')) {
            throw new \RuntimeException('AI is not configured.');
        }
        $response = Http::withToken(config('services.openai.api_key'))->timeout(120)->connectTimeout(10)
            ->post('https://api.openai.com/v1/responses', ['store' => false, ...$parameters]);
        if ($response->failed()) {
            throw new \RuntimeException('AI provider request failed ('.$response->status().').');
        }
        $data = $response->json();
        if (! is_array($data) || in_array($data['status'] ?? '', ['failed', 'incomplete'], true)) {
            throw new \RuntimeException('AI response was incomplete.');
        }

        return $data;
    }

    public function text(array $response): string
    {
        $text = '';
        foreach ($response['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $part) {
                if (($part['type'] ?? null) === 'output_text') {
                    $text .= $part['text'] ?? '';
                }
            }
        }

        return $text;
    }
}
