<?php

namespace App\Jobs;

use App\Models\AiRequest;
use App\Models\Card;
use App\Models\StatementPreview;
use App\Models\User;
use App\Services\AiPdfExtractionService;
use App\Services\StatementTextExtractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExtractStatementPdf implements ShouldQueue
{
    use Queueable;

    public int $timeout = 150;

    public int $tries = 1;

    public function __construct(public string $requestId)
    {
        $this->onConnection('database')->onQueue('ai');
    }

    public function handle(): void
    {
        $request = AiRequest::find($this->requestId);
        if (! $request || $request->status !== 'processing') {
            return;
        }
        $preview = StatementPreview::find($request->preview_id);
        $user = User::find($request->user_id);
        if (! $preview || ! $user || $user->suspended_at || $preview->expires_at->isPast()) {
            $this->failed(new \RuntimeException('Request is no longer available.'));

            return;
        }
        if ($preview->card_id && ! Gate::forUser($user)->allows('update', Card::find($preview->card_id))) {
            $this->failed(new \RuntimeException('Card access is unavailable.'));

            return;
        }
        if (! Storage::disk('local')->exists($preview->file_path)) {
            $this->failed(new \RuntimeException('PDF is no longer available.'));

            return;
        }
        $started = microtime(true);
        if (AiRequest::whereKey($request->id)->where('status', 'processing')->whereNull('started_at')->update(['started_at' => now()]) !== 1) {
            return;
        }
        $file = new UploadedFile(Storage::disk('local')->path($preview->file_path), $preview->original_filename, 'application/pdf', null, true);
        try {
            $result = app(AiPdfExtractionService::class)->extract($file);
        } catch (\Throwable) {
            $result = app(StatementTextExtractionService::class)->extractStatement($file);
            $prefill = app(StatementTextExtractionService::class)->extract($file);
            $result = [...$result, 'card' => $prefill['card'], 'document_recognized' => $prefill['document_recognized'], 'suggested_benefits' => [], 'source' => 'local'];
            $result['warnings'][] = 'AI reading was unavailable. Local extraction was used; check for missing fields or transactions.';
        }
        $status = ($result['analyzed'] ?? false) ? 'ready' : 'failed';
        $usage = $result['usage'] ?? [];
        unset($result['usage']);
        // A deletion or expired draft must not be resurrected by a late job.
        if (! $preview->fresh() || ! $request->fresh()) {
            return;
        }
        $preview->update(['result' => $result]);
        $request->update(['status' => $status, 'usage' => $usage, 'duration_ms' => (int) ((microtime(true) - $started) * 1000), 'finished_at' => now()]);
        Log::info('AI extraction finished', ['request_id' => $request->id, 'status' => $status, 'source' => $result['source'], 'duration_ms' => $request->duration_ms]);
    }

    public function failed(?\Throwable $exception): void
    {
        $request = AiRequest::find($this->requestId);
        if (! $request || $request->status !== 'processing') {
            return;
        }
        $message = 'PDF analysis could not finish. Retry reading the statement or save its PDF only.';
        StatementPreview::whereKey($request->preview_id)->update(['result' => ['analyzed' => false, 'message' => $message, 'statement' => [], 'transactions' => []]]);
        $request->update(['status' => 'failed', 'error' => $message, 'finished_at' => now()]);
    }
}
