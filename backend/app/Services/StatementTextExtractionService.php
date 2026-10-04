<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;

/**
 * Fallback for when the AI API is unavailable and the upload is a PDF
 * statement: shells out to `pdftotext` and regex-matches the handful of
 * fields that appear in a clearly labeled summary block on standard Indian
 * bank e-statements. No web search, so no benefit suggestions — just the
 * fields a plain text layer can expose.
 *
 * Account-summary fields use a bounded opening window to avoid fee-schedule
 * matches. Product headers and explicitly labeled reward summaries are read
 * across all pages, since these can appear later in the statement.
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

        $document = $result->output();
        $text = substr($document, 0, self::SEARCH_WINDOW);

        $data = [
            'bank_name' => $this->extractBankName($text),
            'card_name' => $this->extractCardName($document),
            'reward_point_balance' => $this->extractRewardBalance($document),
            'total_limit' => $this->extractCreditLimit($text),
            'current_outstanding' => $this->extractOutstanding($text),
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
            'message' => 'Extracted locally from the PDF. Please review the fields and fill in missing values.',
            'card' => $this->sanitizer->sanitizeCard($data),
            'suggested_benefits' => [],
        ];
    }

    public function extractStatement(UploadedFile $file): array
    {
        try {
            $result = Process::timeout(15)->run(['pdftotext', '-layout', $file->getRealPath(), '-']);
        } catch (\Throwable $error) {
            report($error);

            return $this->statementFailure('Local PDF extraction is unavailable. Please enter the fields manually.');
        }
        if (! $result->successful()) {
            return $this->statementFailure(preg_match('/password|encrypted/i', $result->errorOutput())
                ? 'This PDF is password protected. Upload an unlocked copy to extract data.'
                : 'This PDF could not be read. Upload a text-readable copy or enter the fields manually.');
        }
        $document = $result->output();
        $text = substr($document, 0, self::SEARCH_WINDOW);
        $summary = [
            'statement_date' => $this->extractDate($text, 'Statement\s*Date'),
            'due_date' => $this->extractDate($text, 'Payment\s*Due\s*Date|Due\s*Date'),
            'total_due' => $this->extractOutstanding($text),
            'minimum_due' => $this->extractOutstanding($text, 'Minimum\s*(?:Amount\s*)?Due'),
            'credit_limit' => $this->extractCreditLimit($text),
            'reward_point_balance' => $this->extractRewardBalance($document),
            'last_four_digits' => $this->extractLastFourDigits($text),
        ];
        if (array_filter($summary, fn ($value) => $value !== null) === []) {
            return $this->statementFailure('No statement fields could be read. For scanned PDFs, upload a text-readable copy or fill in the fields manually.');
        }
        $transactions = [];
        // SBI rows expose a date, narration and a terminal debit/credit marker.
        // Other layouts remain available for review without guessed transactions.
        foreach (preg_split('/\r?\n/', $document) as $line) {
            if (! preg_match('/^\h*(\d{2}\h+[A-Za-z]{3}\h+\d{2,4})\h+(.+?)\h{2,}([\d,]+\.\d{2})\h+([DC])\h*$/i', $line, $row)) {
                continue;
            }
            if (preg_match('/PAYMENT\s+RECEIVED|PAYMENT\s+THANK|AUTOPAY|AUTO\s+DEBIT|BILL\s+PAYMENT/i', $row[2])) {
                continue;
            }
            $date = $this->parseDate($row[1]);
            $amount = (float) str_replace(',', '', $row[3]);
            if ($date && $amount > 0 && $amount <= 9999999999.99) {
                $transactions[] = ['date' => $date, 'description' => trim($row[2]), 'amount' => $amount,
                    'direction' => strtoupper($row[4]) === 'C' ? 'credit' : 'purchase', 'category' => null];
            }
        }
        $warnings = ['Extracted locally without AI. Check every field and transaction against the PDF; unsupported layouts or wrapped rows may be omitted.'];
        if (count($transactions) > 200) {
            $warnings[] = 'Extraction reached the 200-row limit. Check the PDF for omitted transactions.';
        }
        if (in_array(null, $summary, true)) {
            $warnings[] = 'Some fields were not readable and remain blank. Only visible card digits are used.';
        }

        return ['analyzed' => true, 'message' => null, 'warnings' => $warnings,
            'statement' => $summary, 'transactions' => array_slice($transactions, 0, 200)];
    }

    private function statementFailure(string $message): array
    {
        return ['analyzed' => false, 'message' => $message, 'statement' => [], 'transactions' => []];
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

    private function extractCardName(string $text): ?string
    {
        // Statement mastheads identify the actual product. Fee schedules list
        // many other products, so never infer a name from those rows.
        preg_match_all('/^([^\r\n]+?)\h{2,}Monthly\h+Statement\b/im', $text, $matches);
        $names = [];
        foreach ($matches[1] as $header) {
            if (preg_match('/\b(BPCL\h+SBI\h+Card(?:\h+OCTANE)?)\b/i', $header, $product)) {
                $names[] = preg_replace('/\h+/', ' ', $product[1]);
            }
        }
        preg_match_all('/^\h*(?:Card\h+(?:Name|Product)|Product\h+Name)\h*:\h*([^\r\n]+)$/im', $text, $labeled);
        foreach ($labeled[1] as $name) {
            $names[] = trim($name);
        }
        $names = array_values(array_unique($names));

        return count($names) === 1 ? $names[0] : null;
    }

    private function extractRewardBalance(string $text): ?float
    {
        $balances = [];
        // SBI's Shop & Smile table: opening, earned, redeemed, closing.
        // Restrict matching to the named rewards section, never account balance.
        preg_match_all('/(?:SHOP\h*&\h*SMILE\h+SUMMARY|REWARD\h+POINTS?\h+SUMMARY)([^\f]{0,1600})/is', $text, $sections);
        foreach ($sections[1] as $section) {
            if (preg_match('/Previous\h+Balance[^\r\n]*Earned[^\r\n]*Closing\h+Balance[^\r\n]*\R(?:[^\r\n]*\R){0,3}?\h*([\d,]+)\h{2,}([\d,]+)\h{2,}([\d,]+)\h{2,}([\d,]+)\b/i', $section, $row)) {
                $balances[] = (float) str_replace(',', '', $row[4]);
            }
        }
        preg_match_all('/^\h*(?:Available\h+Reward\h+Points|Reward\h+Points\h+Balance|Closing\h+Reward\h+Points\h+Balance)\h*:\h*([\d,]+(?:\.\d+)?)\h*$/im', $text, $labeled);
        foreach ($labeled[1] as $balance) {
            $balances[] = (float) str_replace(',', '', $balance);
        }
        $balances = array_values(array_unique($balances));

        return count($balances) === 1 ? $balances[0] : null;
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
        $date = $this->extractDate($text, $labelAlternation);

        return $date ? (int) substr($date, 8, 2) : null;
    }

    private function extractDate(string $text, string $labelAlternation): ?string
    {
        $pattern = '\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4}|[A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4}|\d{2}[/-]\d{2}[/-]\d{4}|\d{4}-\d{2}-\d{2}';
        if (preg_match('~(?:'.$labelAlternation.').{0,700}?('.$pattern.')~is', $text, $match)) {
            return $this->parseDate($match[1]);
        }

        return null;
    }

    private function parseDate(string $value): ?string
    {
        $value = preg_replace('/\s+/', ' ', trim($value));
        if (preg_match('/^(\d{1,2} [A-Za-z]{3}) (\d{2})$/', $value, $short)) {
            $value = $short[1].' '.((int) $short[2] >= 70 ? '19' : '20').$short[2];
        }
        foreach (['!d M Y', '!d M y', '!d F Y', '!F d, Y', '!F d Y', '!M d, Y', '!M d Y', '!d/m/Y', '!d-m-Y', '!Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date && (! $errors || (! $errors['warning_count'] && ! $errors['error_count']))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function extractOutstanding(string $text, string $label = '(?:Total\s*(?:Amount\s*)?Due|Total\s*Outstanding|Outstanding\s*Balance)'): ?float
    {
        $lines = preg_split('/\r?\n/', $text);
        foreach ($lines as $index => $line) {
            if (! preg_match('/(?:^\h*|\h{2,})\*{0,2}'.$label.'\b(.*)$/i', $line, $labelMatch)) {
                continue;
            }
            if (preg_match('/^(?:[\h:()\-₹`*]|INR|Rs\.?)*([\d,]+(?:\.\d{2})?)/i', $labelMatch[1], $m)) {
                return (float) str_replace(',', '', $m[1]);
            }
            // The first column below a summary heading holds its amount. Do
            // not continue into later boilerplate occurrences of the label.
            foreach (array_slice($lines, $index + 1, 5) as $valueLine) {
                // SBI minimum due shares its row with unrelated identifiers
                // on the left. Its monetary value is the rightmost column.
                if (str_starts_with($label, 'Minimum') && preg_match('/\h{2,}([\d,]+\.\d{2})\h*$/', $valueLine, $right)) {
                    return (float) str_replace(',', '', $right[1]);
                }
                if (preg_match('/^\h*(?:[`₹]|Rs\.?|INR)?\h*([\d,]+(?:\.\d{2})?)/i', $valueLine, $m)) {
                    return (float) str_replace(',', '', $m[1]);
                }
            }

            return null;
        }

        return null;
    }

    private function extractLastFourDigits(string $text): ?string
    {
        preg_match_all('/(?:card\s*(?:number|no\.?)\s*[:\-]?\s*)?((?:\d[ \-]*){0,6}(?:[X*•●][ \-]*){4,14}(?:\d[ \-]*){4})(?!\d)/iu', $text, $matches);
        $candidates = [];
        foreach ($matches[1] as $masked) {
            $digits = preg_replace('/\D/', '', $masked);
            $candidates[] = substr($digits, -4);
        }
        preg_match_all('/card\s*(?:number|no\.?)\s*[:\-]?\s*((?:\d[ \-]*){13,19})(?!\d)/i', $text, $full);
        foreach ($full[1] as $number) {
            $candidates[] = substr(preg_replace('/\D/', '', $number), -4);
        }
        $candidates = array_values(array_unique($candidates));

        return count($candidates) === 1 ? $candidates[0] : null;
    }
}
