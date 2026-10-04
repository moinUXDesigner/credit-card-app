<?php

namespace Tests\Unit;

use App\Services\StatementAnalysisService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class StatementAnalysisServiceTest extends TestCase
{
    public function test_encrypted_pdf_requests_unlocked_copy_without_provider_call(): void
    {
        Http::fake();
        Process::fake(['*' => Process::result(errorOutput: 'Incorrect password', exitCode: 1)]);
        $result = app(StatementAnalysisService::class)->analyze(UploadedFile::fake()->create('protected.pdf', 1, 'application/pdf'));
        $this->assertFalse($result['analyzed']);
        $this->assertStringContainsString('unlocked', $result['message']);
        Http::assertNothingSent();
    }

    public function test_local_summary_and_transactions_work_even_with_an_ai_key_configured(): void
    {
        config(['services.openai.api_key' => 'must-not-be-used']);
        Http::fake();
        Process::fake(['*' => Process::result(output: "SBI Card\nCard Number: XXXX XXXX XXXX 0001\nStatement Date: 01 Oct 2026\nPayment Due Date: 21 Oct 2026\nTotal Amount Due: 22471.00\n**Minimum Amount Due ( ` )\nSTMT No. : TEST\nCKYC No. : 00000000000000                  449.00\nCredit Limit: 391000.00\nReward Points Balance: 18791\n03 Sep 26   PHARMACY SHOP     100.50 D\n04 Sep 26   REFUND SHOP     50.00 C\n06 Sep 26   PAYMENT RECEIVED     500.00 C\n")]);
        $result = app(StatementAnalysisService::class)->analyze(UploadedFile::fake()->create('statement.pdf', 1, 'application/pdf'));
        $this->assertTrue($result['analyzed']);
        $this->assertSame(['statement_date' => '2026-10-01', 'due_date' => '2026-10-21', 'total_due' => 22471.0, 'minimum_due' => 449.0, 'credit_limit' => 391000.0, 'reward_point_balance' => 18791.0, 'last_four_digits' => '0001'], $result['statement']);
        $this->assertCount(2, $result['transactions']);
        $this->assertSame('2026-09-03', $result['transactions'][0]['date']);
        $this->assertSame('credit', $result['transactions'][1]['direction']);
        Http::assertNothingSent();
    }

    public function test_invalid_dates_and_image_only_pdfs_are_not_fabricated(): void
    {
        Http::fake();
        Process::fake(['*' => Process::result(output: "SBI Card\nStatement Date: 30/02/2026\nTotal Amount Due: 0.00")]);
        $result = app(StatementAnalysisService::class)->analyze(UploadedFile::fake()->create('statement.pdf', 1, 'application/pdf'));
        $this->assertTrue($result['analyzed']);
        $this->assertNull($result['statement']['statement_date']);
        $this->assertSame(0.0, $result['statement']['total_due']);
        Process::fake(['*' => Process::result(output: '')]);
        $this->assertFalse(app(StatementAnalysisService::class)->analyze(UploadedFile::fake()->create('scan.pdf', 1, 'application/pdf'))['analyzed']);
        Http::assertNothingSent();
    }
}
