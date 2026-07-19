<?php

namespace Tests\Unit;

use App\Services\CardFieldSanitizer;
use App\Services\StatementTextExtractionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class StatementTextExtractionServiceTest extends TestCase
{
    // Layout/spacing reproduced from a real SBI Card e-statement (via
    // `pdftotext -layout`); cardholder name/address/GSTIN replaced with
    // placeholders. Only exposes the last 2 digits of the card number,
    // same as the real statement's masking.
    private const SBI_STYLE_TEXT = <<<'TEXT'
        GSTIN of SBI Card : 00TESTGSTIN0Z0      Stmt/Debit Note/Credit Note/Tax Invoice                   (ORIGINAL FOR RECIPIENT)

                TEST CARDHOLDER                                                             Credit Card Number

                                                                                           XXXX XXXX XXXX XX45

        PLACE OF SUPPLY : TEST/00/TEST STATE                                               *Total Amount Due ( ` )
                                                                                                  47,098.00
        STMT No.          : J00000000000
        CKYC No.          : 00000000000000                                                 **Minimum Amount Due ( ` )
                                                                                                    2,766.00
                                                                                                                          Pay Now

        Credit Limit ( ` ) (including cash)     Cash Limit ( ` )(as part of credit limit)  Statement Date
               3,91,000.00                             1,17,000.00                         01 Jul 2026

        Available Credit Limit ( ` )            Available Cash Limit ( ` )                 Payment Due Date
              2,74,873.56                         1,17,000.00                               21 Jul 2026
        TEXT;

    // Layout reproduced from a real ICICI Bank e-statement; cardholder
    // details redacted. Exposes the full last 4 digits, unlike the SBI
    // format above.
    private const ICICI_STYLE_TEXT = <<<'TEXT'
        CREDIT CARD STATEMENT

        TEST CARDHOLDER
        TEST ADDRESS LINE

                        STATEMENT DATE                                                                          Ace your Digital Banking, with iPlay videos, on ICICI Bank's iMobile Pay.

                     July 12, 2026                                                                              Scan the OR Code, to know about the Credit Card services,

                      PAYMENT DUE DATE
                                                                                     Scan to watch iPlay video                                         T&C Apply
                     July 30, 2026

                Total Amount due                                                     Previous Balance              Purchases / Charges          Cash Advances                                        -  Payments / Credits

                 `32,268.00                                         =                                           +                       +

                                                                                     `13,504.00                    `32,268.00                   `0.00                                                   `13,504.00

        Minimum Amount due                                                     CREDIT SUMMARY

             `1,620.00                                                         Credit Limit (Including cash) Available Credit (Including cash)   Cash Limit                                             Available Cash

        Interest will be charged if your                                             `2,00,000.00                  `1,67,732.00                 `20,000.00                                                   `0.00
         total amount due is not paid

                              SPENDS OVERVIEW                                  Date        SerNo.                  Transaction Details                 Reward                                             Intl.#  Amount (in`)
                                                                                                                                                        Points                                          amount
                                                                       18/06/2026 13621470067              4315XXXXXXXX9002                    AMAZON PAY IN E COMMERC BANGALORE                                        8,404.00
        TEXT;

    private function fakeFile(): UploadedFile
    {
        return UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf');
    }

    private function service(): StatementTextExtractionService
    {
        return new StatementTextExtractionService(new CardFieldSanitizer());
    }

    public function test_extracts_fields_from_sbi_style_statement(): void
    {
        Process::fake(['*pdftotext*' => Process::result(output: self::SBI_STYLE_TEXT)]);

        $result = $this->service()->extract($this->fakeFile());

        $this->assertTrue($result['document_recognized']);
        $this->assertSame('low', $result['confidence']);
        $this->assertSame('SBI Card', $result['card']['bank_name']);
        $this->assertEqualsWithDelta(391000.0, $result['card']['total_limit'], 0.01);
        $this->assertSame(1, $result['card']['statement_day']);
        $this->assertSame(21, $result['card']['due_day']);
        // Only 2 digits are visible in this mask style — must stay null,
        // not be padded or guessed.
        $this->assertNull($result['card']['last_four_digits']);
        $this->assertSame([], $result['suggested_benefits']);
    }

    public function test_extracts_fields_from_icici_style_statement(): void
    {
        Process::fake(['*pdftotext*' => Process::result(output: self::ICICI_STYLE_TEXT)]);

        $result = $this->service()->extract($this->fakeFile());

        $this->assertTrue($result['document_recognized']);
        $this->assertSame('ICICI', $result['card']['bank_name']);
        $this->assertEqualsWithDelta(200000.0, $result['card']['total_limit'], 0.01);
        $this->assertSame(12, $result['card']['statement_day']);
        $this->assertSame(30, $result['card']['due_day']);
        $this->assertSame('9002', $result['card']['last_four_digits']);
    }

    public function test_returns_unrecognized_when_pdftotext_process_fails(): void
    {
        Process::fake(['*pdftotext*' => Process::result(output: '', errorOutput: 'boom', exitCode: 1)]);

        $result = $this->service()->extract($this->fakeFile());

        $this->assertFalse($result['document_recognized']);
        $this->assertSame('none', $result['confidence']);
        $this->assertNull($result['card']['total_limit']);
    }

    public function test_returns_unrecognized_when_nothing_matches(): void
    {
        Process::fake(['*pdftotext*' => Process::result(output: 'This is not a credit card statement at all.')]);

        $result = $this->service()->extract($this->fakeFile());

        $this->assertFalse($result['document_recognized']);
        $this->assertSame('none', $result['confidence']);
    }

    public function test_does_not_match_boilerplate_credit_limit_mentions_far_into_the_document(): void
    {
        // Real summary block near the top, plus a much-later, unrelated
        // "Credit Limit" mention (as seen in real T&C sections) with a
        // small, wrong-looking number nearby — must not be picked up.
        $text = self::ICICI_STYLE_TEXT.str_repeat(' ', 15000).'Amount above Credit Limit 3.60';

        Process::fake(['*pdftotext*' => Process::result(output: $text)]);

        $result = $this->service()->extract($this->fakeFile());

        $this->assertEqualsWithDelta(200000.0, $result['card']['total_limit'], 0.01);
    }
}
