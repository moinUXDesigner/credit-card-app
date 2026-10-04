<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Statement;
use App\Models\StatementPreview;
use App\Services\StatementImportService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StatementPreviewController extends Controller
{
    public function store(Request $request, StatementImportService $service)
    {
        $data = $request->validate([
            'file' => 'required_without:statement_id|file|mimes:pdf|max:15360',
            'card_id' => 'nullable|integer|exists:cards,id', 'statement_id' => 'nullable|integer|exists:statements,id',
        ]);
        $statement = isset($data['statement_id']) ? Statement::findOrFail($data['statement_id']) : null;
        $card = $statement?->card ?? (isset($data['card_id']) ? Card::findOrFail($data['card_id']) : null);
        if ($card) {
            $this->authorize('update', $card);
        }
        abort_if($statement && ! in_array($statement->analysis_status, ['pending', 'failed'], true), 422, 'This statement was already imported.');
        $file = $request->file('file');
        if ($statement) {
            abort_unless(Storage::disk('local')->exists($statement->file_path), 410, 'The PDF is unavailable. Upload it again.');
            $file = new UploadedFile(Storage::disk('local')->path($statement->file_path), $statement->original_filename, 'application/pdf', null, true);
        }
        $preview = $service->preview($file, $request->user()->id, $card, $statement);
        if ($statement) {
            $statement->update(['preview_id' => $preview->id]);
        }

        $payload = $service->payload($preview, $card);

        return response()->json($payload, $payload['status'] === 'processing' ? 202 : 201);
    }

    public function show(Request $request, StatementPreview $statementPreview, StatementImportService $service)
    {
        abort_unless($statementPreview->user_id === $request->user()->id, 403);
        $card = $statementPreview->card_id ? Card::findOrFail($statementPreview->card_id) : null;
        if ($card) {
            $this->authorize('update', $card);
        }
        abort_if($statementPreview->expires_at->isPast(), 410, 'Preview expired. Analyze the PDF again.');

        return response()->json($service->payload($statementPreview, $card));
    }
}
