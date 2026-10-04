<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStatementRequest;
use App\Http\Resources\StatementResource;
use App\Models\Card;
use App\Models\Statement;
use App\Models\StatementPreview;
use App\Models\User;
use App\Services\SpendAggregationService;
use App\Services\StatementImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatementController extends Controller
{
    public function index(Card $card): JsonResponse
    {
        $this->authorize('view', $card);

        $statements = $card->statements()->with('transactions')->orderByDesc('billing_year')->orderByDesc('billing_month')->get();

        return response()->json(StatementResource::collection($statements));
    }

    public function store(StoreStatementRequest $request, Card $card): JsonResponse
    {
        $this->authorize('update', $card);
        $service = app(StatementImportService::class);
        if ($request->hasFile('file') && ! $request->has('preview_id')) {
            return $this->stage($request, $card, $service);
        }
        $data = $request->validated();
        User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
        unset($data['file']);
        $hash = hash('sha256', json_encode([$card->id, $data]));
        $receipt = DB::table('statement_import_receipts')
            ->where('user_id', $request->user()->id)->where('operation_id', $data['idempotency_key'])->first();
        if ($receipt) {
            abort_if($receipt->request_hash !== $hash, 422, 'Import key was reused with different content.');
            abort_unless($receipt->statement_id, 410, 'The imported statement was deleted.');

            return response()->json(new StatementResource(Statement::findOrFail($receipt->statement_id)->load('transactions')));
        }
        $preview = StatementPreview::whereKey($data['preview_id'])->lockForUpdate()->first();
        if (! $preview || $preview->expires_at->isPast()) {
            $staged = $card->statements()->where('preview_id', $data['preview_id'])->where('analysis_status', 'pending')->first();
            if ($staged) {
                return response()->json(new StatementResource($staged->load('transactions')));
            }
        }
        if ((! $preview || $preview->expires_at->isPast()) && $request->hasFile('file')) {
            // A durable offline upload outlived its preview. Preserve its PDF
            // as pending and require a fresh review, never apply stale data.
            return $this->stage($request, $card, $service);
        }
        abort_unless($preview, 410, 'Preview expired. Analyze the PDF again.');
        abort_unless($preview->user_id === $request->user()->id, 403);
        $existing = $card->statements()->where('fingerprint', $preview->fingerprint)->where('analysis_status', 'completed')->first();
        if (! $existing && (int) $data['revision'] !== (int) $card->revision) {
            return response()->json(['message' => 'The card changed. Reload the preview before confirming.', 'server' => $card, 'revision' => $card->revision], 409);
        }
        $statement = $service->confirm($card, $preview, $data);
        DB::table('statement_import_receipts')->insert([
            'user_id' => $request->user()->id, 'operation_id' => $data['idempotency_key'], 'request_hash' => $hash,
            'statement_id' => $statement->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(new StatementResource($statement->load('transactions')), $existing ? 200 : 201);
    }

    private function stage(StoreStatementRequest $request, Card $card, StatementImportService $service): JsonResponse
    {
        $fingerprint = hash_file('sha256', $request->file('file')->getRealPath());
        $existing = $card->statements()->where('fingerprint', $fingerprint)->first();
        if ($existing) {
            return response()->json(new StatementResource($existing->load('transactions')));
        }
        $preview = $service->preview($request->file('file'), $request->user()->id, $card);
        $statement = $card->statements()->create([
            'file_path' => $preview->file_path, 'original_filename' => $preview->original_filename,
            'fingerprint' => $fingerprint, 'preview_id' => $preview->id,
            'billing_month' => $request->input('billing_month'), 'billing_year' => $request->input('billing_year'),
            'analysis_status' => 'pending', 'analysis_message' => 'Awaiting review. No balances or spend have been changed.',
        ]);
        $preview->update(['statement_id' => $statement->id]);

        return response()->json(new StatementResource($statement->load('transactions')), 201);
    }

    public function show(Statement $statement): JsonResponse
    {
        $this->authorize('view', $statement);

        return response()->json(new StatementResource($statement->load('transactions')));
    }

    public function destroy(Statement $statement): JsonResponse
    {
        $this->authorize('delete', $statement);

        $card = $statement->card;
        $periods = $statement->transactions()
            ->get()
            ->map(fn ($t) => ['year' => (int) $t->transaction_date->format('Y'), 'month' => (int) $t->transaction_date->format('n')])
            ->unique(fn ($p) => "{$p['year']}-{$p['month']}")
            ->values()
            ->all();

        app(StatementImportService::class)->restoreSummary($card, $statement);
        $path = $statement->file_path;
        DB::afterCommit(fn () => Storage::disk('local')->delete($path));
        $statement->delete();

        if ($periods !== []) {
            $this->recomputeSpendForPeriods($card, $periods);
        }

        return response()->json(null, 204);
    }

    public function download(Statement $statement): StreamedResponse
    {
        $this->authorize('view', $statement);

        return Storage::disk('local')->download($statement->file_path, $statement->original_filename);
    }

    /**
     * Recompute MonthlySpendEntry rows for the given (year, month) periods
     * as the full sum of this card's Transaction rows in that period,
     * grouped by category — transactions are the source of truth once they
     * exist, so this stays correct (idempotent) across re-uploads and
     * overlapping statement cycles. Categories with no remaining
     * transactions for the period are zeroed rather than left stale.
     *
     * @param  iterable<array{year: int, month: int}>  $periods
     */
    private function recomputeSpendForPeriods(Card $card, array $periods): void
    {
        app(SpendAggregationService::class)->recompute($card, $periods);
    }
}
