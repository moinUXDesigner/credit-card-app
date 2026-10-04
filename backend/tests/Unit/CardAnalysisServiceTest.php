<?php

namespace Tests\Unit;

use App\Exceptions\CardAnalysisUnavailableException;
use App\Services\CardAnalysisService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Exercises the real request/response wiring to the OpenAI Responses API
 * (built directly on Http::fake() rather than an SDK, since the community
 * PHP client doesn't document support for input_file/input_image or
 * structured-output json_schema on this endpoint).
 */
class CardAnalysisServiceTest extends TestCase
{
    private function fixtureCard(): array
    {
        return [
            'document_recognized' => true,
            'confidence' => 'high',
            'card_name' => 'SBI Octane', 'bank_name' => 'SBI Card', 'last_four_digits' => '7245',
            'network' => 'visa', 'total_limit' => 250000.0, 'statement_day' => 12, 'due_day' => 2,
            'annual_fee_amount' => 1499.0, 'annual_fee_month' => 4, 'waiver_spend_required' => 300000.0,
            'reward_point_balance' => null,
            'reward_rate_general' => 1.0, 'cashback_cap_amount' => 1000.0,
            'forex_markup_percent' => 3.5, 'fuel_surcharge_waiver_percent' => 1.0, 'insurance_cover_amount' => 500000.0,
            'lounge_access' => true,
            'best_categories' => ['fuel'],
            'suggested_benefits' => [
                ['type' => 'lounge', 'title' => 'Domestic lounge access', 'frequency' => 'yearly', 'total_allowed' => 4, 'expiry_date' => null, 'estimated_value' => 2000.0],
                ['type' => 'other', 'title' => 'Welcome bonus voucher', 'frequency' => 'one_time', 'total_allowed' => 1, 'expiry_date' => null, 'estimated_value' => 1000.0],
            ],
        ];
    }

    private function fakeOpenAiResponse(array $card): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'status' => 'completed',
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            ['type' => 'output_text', 'text' => json_encode($card)],
                        ],
                    ],
                ],
            ]),
        ]);
    }

    public function test_pdf_prefill_extracts_locally_without_provider_call(): void
    {
        Http::fake();
        Process::fake(['*pdftotext*' => Process::result(output: "SBI Card\nTotal Amount Due: 22471.00\nReward Points Balance: 18791")]);
        $result = (new CardAnalysisService)->analyze(UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf'));
        $this->assertTrue($result['document_recognized']);
        $this->assertEquals(22471, $result['card']['current_outstanding']);
        $this->assertEquals(18791, $result['card']['reward_point_balance']);
        Http::assertNothingSent();
    }

    public function test_analyze_sends_input_image_block_for_photo(): void
    {
        $this->fakeOpenAiResponse($this->fixtureCard());

        $file = UploadedFile::fake()->create('card.jpg', 50, 'image/jpeg');

        (new CardAnalysisService)->analyze($file);

        Http::assertSent(function ($request) {
            $fileBlock = $request->data()['input'][0]['content'][1];

            return $fileBlock['type'] === 'input_image'
                && str_starts_with($fileBlock['image_url'], 'data:image/jpeg;base64,');
        });
    }

    public function test_analyze_throws_when_response_status_is_failed(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'failed'])]);

        $file = UploadedFile::fake()->create('card.jpg', 100, 'image/jpeg');

        $this->expectException(CardAnalysisUnavailableException::class);

        (new CardAnalysisService)->analyze($file);
    }

    public function test_analyze_throws_when_no_output_text_found(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => []])]);

        $file = UploadedFile::fake()->create('card.jpg', 100, 'image/jpeg');

        $this->expectException(CardAnalysisUnavailableException::class);

        (new CardAnalysisService)->analyze($file);
    }
}
