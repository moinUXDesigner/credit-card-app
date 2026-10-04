<?php

use App\Jobs\AnswerCardChat;
use App\Jobs\ExtractStatementPdf;
use App\Models\AiRequest;
use App\Services\StatementImportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('statements:cleanup-previews', function () {
    app(StatementImportService::class)->cleanup();
    $this->info('Expired statement previews removed.');
});

Artisan::command('ai:expire-requests', function () {
    $count = 0;
    foreach (AiRequest::where('status', 'processing')->where('updated_at', '<', now()->subMinutes(10))->get() as $request) {
        if ($request->kind === 'chat') {
            (new AnswerCardChat($request->id))->failed(null);
        } else {
            (new ExtractStatementPdf($request->id))->failed(null);
        }
        $count++;
    }
    $this->info("Expired {$count} stalled AI requests.");
});
