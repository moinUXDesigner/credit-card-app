<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;

/**
 * Fallback for when the Claude API is unavailable and the upload is a PDF
 * statement: shells out to `pdftotext` and regex-matches the handful of
 * fields that appear in a clearly labeled summary block on standard Indian
 * bank e-statements. No web search, so no benefit suggestions — just the
 * fields a plain text layer can expose.
 *
 * Only the first ~6000 characters of the extracted text are searched. The
 * account-summary block always appears near the top of page 1 in the
 * formats this was validated against (SBI, ICICI); searching the full
 * document risks false matches from T&C boilerplate and fee schedules
 * later on, which repeat labels like "Credit Limit" with unrelated nearby
 * numbers.
 */
class StatementTextExtractionService
{
    private const SEARCH_WINDOW = 8000;

    private const KNOWN_BANKS = [
        'SBI Card', 'SBI', 'HDFC', 'ICICI', 'Axis Bank', 'Axis', 'Kotak', 'IDFC',
        'Yes Bank', 'Citibank', 'RBL', 'IndusInd', 'American Express', 'Amex',
        'Standard Chartered',
    ];

    public function __construct(private CardFieldSanitizer $sanitizer) {}

    public function extract(UploadedFile $file): array
    {
        $result = Process::timeout(15)->run(['pdftotext', '-layout', $file->getRealPath(), '-']);

        if (! $result->successful()) {
            return $this->unrecognized();
        }

        $text = substr($result->output(), 0, self::SEARCH_WINDOW);

        $data = [
            'bank_name' => $this->extractBankName($text),
            'total_limit' => $this->extractCreditLimit($text),
            'statement_day' => $this->extractDay($text, 'Statement\s*Date'),
            'due_day' => $this->extractDay($text, 'Payment\s*Due\s*Date|Due\s*Date'),
            'last_four_digits' => $this->extractLastFourDigits($text),
        ];

        if (array_filter($data, fn ($v) => $v !== null) === []) {
            return $this->unrecognized();
        }

        return [
            'analyzed' => true,
            'document_recognized' => true,
            'confidence' => 'low',
            'message' => 'AI analysis was unavailable, so we used basic text extraction instead — '
                .'some fields may be missing. Please review and fill in the rest.',
            'card' => $this->sanitizer->sanitizeCard($data),
            'suggested_benefits' => [],
        ];
    }

    private function unrecognized(): array
    {
        return [
            'analyzed' => true,
            'document_recognized' => false,
            'confidence' => 'none',
            'message' => "We couldn't extract any details from this file automatically. Please fill in the details manually.",
            'card' => $this->sanitizer->sanitizeCard([]),
            'suggested_benefits' => [],
        ];
    }

    private function extractBankName(string $text): ?string
    {
        foreach (self::KNOWN_BANKS as $bank) {
            if (stripos($text, $bank) !== false) {
                return $bank;
            }
        }

        return null;
    }

    private function extractCreditLimit(string $text): ?float
    {
        // Window calibrated against real poppler-utils output (25.12.0):
        // label-to-value distance measured at 142 chars (SBI) / 328 chars
        // (ICICI) — different bank layouts insert varying amounts of
        // interleaved column text between the label and its value.
        if (preg_match('/Credit\s*Limit.{0,500}?([\d,]+\.\d{2})/is', $text, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }

        return null;
    }

    private function extractDay(string $text, string $labelAlternation): ?int
    {
        $datePattern = '\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4}|[A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4}';

        // Window calibrated the same way — "Payment Due Date" measured up
        // to 565 chars from its value on the ICICI layout.
        if (preg_match('/(?:'.$labelAlternation.').{0,700}?('.$datePattern.')/is', $text, $m)) {
            $timestamp = strtotime(trim($m[1]));

            return $timestamp !== false ? (int) date('j', $timestamp) : null;
        }

        return null;
    }

    private function extractLastFourDigits(string $text): ?string
    {
        if (preg_match('/\b\d{4}X{6,8}(\d{4})\b/i', $text, $m)) {
            return $m[1];
        }

        return null;
    }
}
