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
        return new StatementTextExtractionService(new CardFieldSanitizer);
    }

    public function test_extracts_fields_from_sbi_style_statement(): void
    {
        Process::fake(['*pdftotext*' => Process::result(output: self::SBI_STYLE_TEXT)]);

        $result = $this->service()->extract($this->fakeFile());

        $this->assertTrue($result['document_recognized']);
        $this->assertSame('low', $result['confidence']);
        $this->assertSame('SBI Card', $result['card']['bank_name']);
        $this->assertEqualsWithDelta(391000.0, $result['card']['total_limit'], 0.01);
        $this->assertEquals(47098, $result['card']['current_outstanding']);
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
        $this->assertEquals(32268, $result['card']['current_outstanding']);
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

    public function test_mask_variants_preserve_leading_zeros_and_ambiguous_masks_remain_unknown(): void
    {
        foreach (['XXXX XXXX XXXX 0001', '****-****-****-0001', '•••• •••• •••• 0001', '4315XXXXXXXX0001'] as $mask) {
            Process::fake(['*pdftotext*' => Process::result(output: "ICICI Card Number: {$mask}\nTotal Due: INR 15000.00\nMinimum Due: 500.00")]);
            $result = $this->service()->extract($this->fakeFile());
            $this->assertSame('0001', $result['card']['last_four_digits']);
            $this->assertEquals(15000, $result['card']['current_outstanding']);
        }
        Process::fake(['*pdftotext*' => Process::result(output: 'ICICI Card Number XXXX XXXX XXXX 0001 and XXXX XXXX XXXX 1234')]);
        $this->assertNull($this->service()->extract($this->fakeFile())['card']['last_four_digits']);
    }

    public function test_numeric_dates_and_summary_heading_before_boilerplate(): void
    {
        Process::fake(['*pdftotext*' => Process::result(output: "YES BANK\nStatement Date: 20/06/2026\nTotal Amount Due:       Cash Limit: 113000\nRs. 537.00             Rs. 33900.00\nPayment Due Date: 10/07/2026\nIf total amount due is not paid\n113000.00")]);
        $result = $this->service()->extract($this->fakeFile());
        $this->assertEquals(537, $result['card']['current_outstanding']);
        $this->assertSame(20, $result['card']['statement_day']);
        $this->assertSame(10, $result['card']['due_day']);
    }

    public function test_reads_product_and_closing_rewards_on_later_pages(): void
    {
        $text = self::SBI_STYLE_TEXT.str_repeat(' ', 9000)."\f"
            ."BPCL SBI Card OCTANE                 Monthly Statement       SBI Card\n"
            ."SHOP & SMILE SUMMARY\n"
            ."                 Redeemed/Expired\n"
            ."Previous Balance    Earned    /Reversed    Closing Balance    Points Expiry Details\n"
            ."                                                             859 points will expire\n"
            ."17999               869      77           18791              30 Nov 2026\n"
            ."\fBPCL SBI Card    499    Fee schedule\nBPCL SBI Card Octane    1499\n";
        Process::fake(['*pdftotext*' => Process::result(output: $text)]);
        $card = $this->service()->extract($this->fakeFile())['card'];
        $this->assertSame('BPCL SBI Card OCTANE', $card['card_name']);
        $this->assertEquals(18791, $card['reward_point_balance']);
        $this->assertEquals(391000, $card['total_limit']);
    }

    public function test_reward_zero_is_preserved_and_conflicting_balances_are_unknown(): void
    {
        Process::fake(['*pdftotext*' => Process::result(output: "SBI Card\nReward Points Balance: 0\n")]);
        $this->assertEquals(0, $this->service()->extract($this->fakeFile())['card']['reward_point_balance']);
        Process::fake(['*pdftotext*' => Process::result(output: "SBI Card\nReward Points Balance: 100\nAvailable Reward Points: 200\n")]);
        $this->assertNull($this->service()->extract($this->fakeFile())['card']['reward_point_balance']);
    }

    public function test_fee_schedule_and_account_closing_balance_are_not_product_or_rewards(): void
    {
        Process::fake(['*pdftotext*' => Process::result(output: "SBI Card\nBPCL SBI Card Octane    1499\nACCOUNT SUMMARY\nClosing Balance: 1000\nReward Points earned: 869\n")]);
        $card = $this->service()->extract($this->fakeFile())['card'];
        $this->assertNull($card['card_name']);
        $this->assertNull($card['reward_point_balance']);
    }
}
