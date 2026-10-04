<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/** Local statement extraction; uploading a statement never calls an AI provider. */
class StatementAnalysisService
{
    public function analyze(UploadedFile $file): array
    {
        return app(StatementTextExtractionService::class)->extractStatement($file);
    }
}
