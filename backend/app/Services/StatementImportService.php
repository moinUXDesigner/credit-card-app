<?php

namespace App\Services;

use App\Jobs\ExtractStatementPdf;
use App\Models\AiRequest;
use App\Models\Card;
use App\Models\Statement;
use App\Models\StatementPreview;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StatementImportService
{
    public const SUMMARY_FIELDS = ['current_outstanding' => 'total_due', 'reward_point_balance' => 'reward_point_balance', 'total_limit' => 'credit_limit'];

    public function preview(UploadedFile $file, int $userId, ?Card $card = null, ?Statement $statement = null): StatementPreview
    {
        $path = $statement?->file_path ?? $file->store('statement-previews', 'local');
        if (! $statement) {
            request()->attributes->set('rollback_files', [$path]);
        }
        $queued = (bool) config('services.openai.api_key');
        $result = $queued ? ['analyzed' => false, 'message' => 'Reading statement…', 'statement' => [], 'transactions' => []] : app(StatementAnalysisService::class)->analyze($file);
        if (! $queued) {
            $prefill = app(StatementTextExtractionService::class)->extract($file);
            $result = [...$result, 'source' => 'local', 'card' => $prefill['card'], 'document_recognized' => $prefill['document_recognized'], 'suggested_benefits' => []];
        }
        $preview = StatementPreview::create([
            'id' => (string) Str::uuid(), 'user_id' => $userId, 'card_id' => $card?->id,
            'statement_id' => $statement?->id, 'file_path' => $path,
            'original_filename' => $statement?->original_filename ?? $file->getClientOriginalName(),
            'fingerprint' => hash_file('sha256', $file->getRealPath()),
            'result' => $result, 'expires_at' => now()->addDay(),
        ]);
        if ($queued) {
            $job = AiRequest::create(['id' => (string) Str::uuid(), 'user_id' => $userId, 'kind' => 'pdf', 'preview_id' => $preview->id, 'model' => config('services.openai.pdf_model', config('services.openai.model'))]);
            ExtractStatementPdf::dispatch($job->id)->afterCommit();
        }

        return $preview;
    }

    public function duplicate(Card $card, array $row): bool
    {
        $description = $this->normalize($row['description']);

        return $card->transactions()->whereDate('transaction_date', $row['transaction_date'])
            ->where('amount', $row['amount'])->where('direction', $row['direction'] ?? 'purchase')->get()
            ->contains(fn ($t) => $this->normalize($t->description) === $description);
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
    }

    public function payload(StatementPreview $preview, ?Card $card): array
    {
        $request = AiRequest::where('preview_id', $preview->id)->latest()->first();
        $summary = $preview->result['statement'] ?? [];
        $rows = array_map(function ($row) use ($card) {
            $row = [
                'transaction_date' => $row['date'] ?? $row['transaction_date'] ?? '',
                'description' => $row['description'], 'amount' => $row['amount'],
                'direction' => $row['direction'] ?? 'purchase', 'category' => $row['category'] ?? 'other',
            ];
            $row['possible_duplicate'] = $card && $row['transaction_date'] && $this->duplicate($card, $row);

            return $row;
        }, $preview->result['transactions'] ?? []);
        $existing = $card?->statements()->where('fingerprint', $preview->fingerprint)->where('analysis_status', 'completed')->first();
        $date = $this->date($summary['statement_date'] ?? null);

        return [
            'status' => $request?->status ?? (($preview->result['analyzed'] ?? false) ? 'ready' : 'failed'),
            'source' => $preview->result['source'] ?? 'local',
            'card' => $preview->result['card'] ?? [], 'document_recognized' => $preview->result['document_recognized'] ?? false, 'suggested_benefits' => [],
            'preview_id' => $preview->id, 'expires_at' => $preview->expires_at->toIso8601String(),
            'original_filename' => $preview->original_filename, 'card_id' => $card?->id,
            'card_revision' => $card?->revision, 'analyzed' => $preview->result['analyzed'] ?? false,
            'message' => $preview->result['message'] ?? null, 'warnings' => $preview->result['warnings'] ?? [],
            'summary' => $summary, 'rows' => $rows, 'duplicate_statement_id' => $existing?->id,
            'apply_defaults' => collect(self::SUMMARY_FIELDS)->mapWithKeys(fn ($source, $field) => [
                $field => $field !== 'total_limit' && isset($summary[$source]) && (! $card || $this->eligible($card, $field, $date)),
            ])->all(),
        ];
    }

    private function date(?string $date): ?string
    {
        return $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
    }

    public function eligible(Card $card, string $field, ?string $date): bool
    {
        $source = $card->summary_provenance[$field] ?? null;
        if (! $source) {
            // Existing legacy statements still establish a historical cutoff.
            $latest = $card->statements()->where('analysis_status', 'completed')->max('statement_date');

            return ! $latest || ($date && $date > substr($latest, 0, 10));
        }

        return $date && $date > ($source['date'] ?? '9999-12-31');
    }

    public function confirm(Card $card, StatementPreview $preview, array $data): Statement
    {
        if ($preview->card_id && $preview->card_id !== $card->id) {
            $this->fail('preview_id', 'This preview belongs to a different card.');
        }
        $existing = $card->statements()->where('fingerprint', $preview->fingerprint)->first();
        if ($existing && $existing->analysis_status === 'completed') {
            return $existing;
        }
        if (! Storage::disk('local')->exists($preview->file_path)) {
            $this->fail('preview_id', 'The PDF is unavailable. Upload it again.');
        }
        if ($preview->expires_at->isPast()) {
            $this->fail('preview_id', 'Preview expired. Analyze the PDF again.');
        }
        if (AiRequest::where('preview_id', $preview->id)->where('status', 'processing')->exists() && ! ($data['save_pdf_only'] ?? false)) {
            $this->fail('preview_id', 'Statement reading is still processing. Wait for it to finish.');
        }
        $summary = $data['summary'];
        $identity = $preview->result['statement']['last_four_digits'] ?? null;
        if ($identity && $identity !== $card->last_four_digits) {
            $this->fail('preview_id', 'The statement card number does not match this card.');
        }
        if (! $identity && ! ($data['acknowledge_identity'] ?? false)) {
            $this->fail('acknowledge_identity', 'Confirm that this PDF belongs to the selected card.');
        }
        $pdfOnly = $data['save_pdf_only'] ?? false;
        if (! ($preview->result['analyzed'] ?? false) && ! $pdfOnly) {
            $this->fail('save_pdf_only', 'Extraction failed. Retry or save the PDF only.');
        }
        $rows = $pdfOnly ? [] : $data['rows'];
        foreach ($rows as $row) {
            if ($this->duplicate($card, $row) && ! isset($row['duplicate_action'])) {
                $this->fail('rows', 'Review possible duplicate transactions and choose skip or keep.');
            }
        }
        $date = $summary['statement_date'] ?? sprintf('%04d-%02d-01', $data['billing_year'], $data['billing_month']);
        $updates = [];
        foreach (self::SUMMARY_FIELDS as $field => $source) {
            if ($pdfOnly || ! ($data['apply_summary'][$field] ?? false) || ! isset($summary[$source])) {
                continue;
            }
            if (! $this->eligible($card, $field, $date)) {
                $this->fail('apply_summary', 'A newer statement or manual/message update supplies '.$field.'. Leave that update unchecked.');
            }
            $updates[$field] = $summary[$source];
        }
        if (isset($updates['total_limit']) && $card->shared_limit_group && Card::where('user_id', $card->user_id)->where('shared_limit_group', $card->shared_limit_group)->whereKeyNot($card->id)->where('total_limit', '!=', $updates['total_limit'])->exists()) {
            $this->fail('apply_summary.total_limit', 'This limit differs from the shared credit limit. Edit the shared group before importing a changed limit.');
        }
        $statement = $existing ?? new Statement;
        $statement->card_id = $card->id;
        $statement->fill([
            'file_path' => $preview->file_path, 'original_filename' => $preview->original_filename,
            'fingerprint' => $preview->fingerprint, 'preview_id' => null,
            'billing_month' => $data['billing_month'], 'billing_year' => $data['billing_year'],
            'analysis_status' => $pdfOnly ? 'failed' : 'completed',
            'analysis_message' => $pdfOnly ? 'PDF saved without importing data.' : ($preview->result['message'] ?? null),
            'statement_date' => $summary['statement_date'] ?? null, 'due_date' => $summary['due_date'] ?? null,
            'total_due' => $summary['total_due'] ?? null, 'minimum_due' => $summary['minimum_due'] ?? null,
            'extracted_summary' => $summary,
        ]);
        $previous = [];
        foreach ($updates as $field => $value) {
            $previous[$field] = ['value' => $card->$field, 'source' => $card->summary_provenance[$field] ?? null];
        }
        $statement->summary_previous = $previous;
        $statement->save();
        $periods = [];
        foreach ($rows as $row) {
            if (($row['duplicate_action'] ?? null) === 'skip') {
                continue;
            }
            unset($row['duplicate_action']);
            $row['statement_id'] = $statement->id;
            $row['source'] = 'statement';
            $card->transactions()->create($row);
            $d = Carbon::parse($row['transaction_date']);
            $periods[$d->format('Y-m')] = ['year' => $d->year, 'month' => $d->month];
        }
        $provenance = $card->summary_provenance ?? [];
        foreach ($updates as $field => $value) {
            $card->$field = $value;
            $provenance[$field] = ['type' => 'statement', 'statement_id' => $statement->id, 'date' => $date, 'previous' => $previous[$field]];
        }
        $card->summary_provenance = $provenance;
        $card->save();
        if ($periods) {
            app(SpendAggregationService::class)->recompute($card, array_values($periods));
        }
        $preview->card_id = $card->id;
        $preview->statement_id = $statement->id;
        $preview->save();

        return $statement;
    }

    public function restoreSummary(Card $card, Statement $statement): void
    {
        $provenance = $card->summary_provenance ?? [];
        foreach (self::SUMMARY_FIELDS as $field => $source) {
            if (($provenance[$field]['statement_id'] ?? null) !== $statement->id) {
                continue;
            }
            $previous = $statement->summary_previous[$field] ?? null;
            // Walk the saved source chain, including already deleted statements.
            while ($previous && ($previous['source']['type'] ?? null) === 'statement') {
                $prior = Statement::find($previous['source']['statement_id']);
                if ($prior) {
                    break;
                }
                $previous = $previous['source']['previous'] ?? null;
            }
            $candidate = $card->statements()->whereKeyNot($statement->id)->where('analysis_status', 'completed')->whereNotNull('statement_date')->where('statement_date', '<=', $statement->statement_date ?? now()->toDateString())->orderByDesc('statement_date')->get()->first(fn ($s) => isset($s->extracted_summary[$source]));
            if ($candidate && (! ($previous['source']['date'] ?? null) || $candidate->statement_date->toDateString() > $previous['source']['date'])) {
                $previous = ['value' => $candidate->extracted_summary[$source], 'source' => ['type' => 'statement', 'statement_id' => $candidate->id, 'date' => $candidate->statement_date->toDateString(), 'previous' => $candidate->summary_previous[$field] ?? $previous]];
            }
            if ($previous) {
                $card->$field = $previous['value'];
                $provenance[$field] = $previous['source'];
            } else {
                unset($provenance[$field]);
            }
        }
        $card->summary_provenance = $provenance;
        $card->save();
    }

    public function markExternal(Card $card, array $fields, string $type): void
    {
        $provenance = $card->summary_provenance ?? [];
        foreach (self::SUMMARY_FIELDS as $field => $source) {
            if (array_key_exists($field, $fields)) {
                $provenance[$field] = ['type' => $type, 'date' => now()->toDateString()];
            }
        }
        $card->summary_provenance = $provenance;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function cleanup(): void
    {
        foreach (StatementPreview::where('expires_at', '<', now())->get() as $preview) {
            if (! Statement::where('file_path', $preview->file_path)->exists() && ! StatementPreview::where('file_path', $preview->file_path)->where('expires_at', '>=', now())->exists()) {
                Storage::disk('local')->delete($preview->file_path);
            }
            $preview->delete();
        }
    }
}
