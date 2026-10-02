<?php

namespace App\Services;

class MessageParser
{
    public function parse(string $text, bool $eml = false): array
    {
        if ($eml) {
            // Accept text/plain MIME bodies, including base64 and quoted-printable.
            $parts = preg_split('/\r?\n--[^\r\n]+\r?\n/', $text);
            $bodies = [];
            foreach ($parts as $part) {
                $split = preg_split('/\r?\n\r?\n/', $part, 2);
                if (count($split) !== 2) {
                    continue;
                }
                [$headers,$body] = $split;
                if (preg_match('/Content-Type:\s*(?!text\/plain)[^\r\n]+/i', $headers) && ! preg_match('/Content-Type:\s*text\/plain/i', $headers)) {
                    continue;
                }
                if (preg_match('/Content-Transfer-Encoding:\s*base64/i', $headers)) {
                    $body = base64_decode(trim($body), true) ?: '';
                } elseif (preg_match('/Content-Transfer-Encoding:\s*quoted-printable/i', $headers)) {
                    $body = quoted_printable_decode($body);
                }
                $bodies[] = $body;
            }
            $text = implode("\n", $bodies);
        }
        abort_unless(mb_check_encoding($text, 'UTF-8'), 422, 'Use a UTF-8 text or email file.');
        $rows = [];
        $summary = [];
        foreach (preg_split('/\r?\n/', $text) as $line) {
            $line = trim($line);
            if (! $line) {
                continue;
            }
            if (preg_match('/(?:total\s+(?:amount\s+)?due|outstanding(?:\s+balance)?)\s*[:=-]?\s*(?:INR|Rs\.?|₹)?\s*([\d,]+(?:\.\d{1,2})?)/iu', $line, $m)) {
                $summary['current_outstanding'] = (float) str_replace(',', '', $m[1]);

                continue;
            }
            if (! preg_match('/(?:INR|Rs\.?|₹)\s*([\d,]+(?:\.\d{1,2})?)/iu', $line, $m)) {
                continue;
            }
            $date = null;
            if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $line, $dm)) {
                $parts = explode('-', $dm[1]);
                if (checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
                    $date = $dm[1];
                }
            }
            $credit = (bool) preg_match('/\b(credited|credit|refund|payment received|paid towards)\b/i', $line);
            $direction = $credit ? 'credit' : (preg_match('/\b(spent|purchase|debited|charged|transaction|paid at)\b/i', $line) ? 'purchase' : null);
            $description = $line;
            if (preg_match('/\b(?:at|towards|to)\s+(.+?)(?=\s+(?:on|using|with|card|ending)\b|$)/i', $line, $desc)) {
                $description = $desc[1];
            }
            $rows[] = ['transaction_date' => $date, 'description' => mb_substr($description, 0, 255), 'amount' => (float) str_replace(',', '', $m[1]), 'direction' => $direction, 'category' => 'other', 'warnings' => array_values(array_filter([$date ? null : 'Enter an unambiguous date (YYYY-MM-DD).', $direction ? null : 'Select purchase or credit.']))];
            if (count($rows) > 200) {
                abort(422, 'Import at most 200 transactions at a time.');
            }
        }
        abort_unless($rows || $summary,422,'No supported transactions or account summary found. Include an INR/Rs amount and transaction details.');

        return ['rows' => $rows, 'summary' => $summary];
    }
}
