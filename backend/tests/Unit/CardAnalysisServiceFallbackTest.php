<?php

namespace Tests\Unit;

use App\Exceptions\CardAnalysisUnavailableException;
use App\Services\CardAnalysisService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Exercises the real CardAnalysisService -> StatementTextExtractionService
 * wiring (not mocked at the controller boundary, unlike
 * tests/Feature/CardAnalysisTest.php) to confirm the PDF fallback actually
 * triggers on an AI failure, and that image uploads deliberately do not
 * get a fallback.
 *
 * The OpenAI API call is simulated as failing via an anonymous subclass
 * overriding the single protected call site
 * (CardAnalysisService::createResponse), rather than faking the outbound
 * HTTP request for every test.
 */
class CardAnalysisServiceFallbackTest extends TestCase
{
    // Same anonymized SBI-style layout used in StatementTextExtractionServiceTest.
    private const SBI_STYLE_TEXT = <<<'TEXT'
        GSTIN of SBI Card : 00TESTGSTIN0Z0      Stmt/Debit Note/Credit Note/Tax Invoice                   (ORIGINAL FOR RECIPIENT)

                TEST CARDHOLDER                                                             Credit Card Number

                                                                                           XXXX XXXX XXXX XX45

        Credit Limit ( ` ) (including cash)     Cash Limit ( ` )(as part of credit limit)  Statement Date
               3,91,000.00                             1,17,000.00                         01 Jul 2026

        Available Credit Limit ( ` )            Available Cash Limit ( ` )                 Payment Due Date
              2,74,873.56                         1,17,000.00                               21 Jul 2026
        TEXT;

    private function serviceWithFailingAiCall(): CardAnalysisService
    {
        return new class extends CardAnalysisService
        {
            protected function createResponse(array $params): array
            {
                throw new \Exception('simulated AI failure');
            }
        };
    }

    public function test_pdf_upload_falls_back_to_text_extraction_when_ai_fails(): void
    {
        Process::fake(['*pdftotext*' => Process::result(output: self::SBI_STYLE_TEXT)]);

        $file = UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf');

        $result = $this->serviceWithFailingAiCall()->analyze($file);

        $this->assertTrue($result['document_recognized']);
        $this->assertSame('low', $result['confidence']);
        $this->assertSame('SBI Card', $result['card']['bank_name']);
        $this->assertEqualsWithDelta(391000.0, $result['card']['total_limit'], 0.01);
        $this->assertSame([], $result['suggested_benefits']);
    }

    public function test_image_upload_does_not_fall_back_when_ai_fails(): void
    {
        // create() (not image()) avoids a hard dependency on the GD extension
        // being installed — this test only cares about the mime type.
        $file = UploadedFile::fake()->create('card.jpg', 50, 'image/jpeg');

        $this->expectException(CardAnalysisUnavailableException::class);

        $this->serviceWithFailingAiCall()->analyze($file);
    }
}
