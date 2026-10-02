<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStatementRequest;
use App\Http\Resources\StatementResource;
use App\Models\Card;
use App\Models\Statement;
use App\Services\StatementAnalysisService;
use App\Services\WaiverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatementController extends Controller
{
    public function __construct(
        private StatementAnalysisService $statementAnalysisService,
        private WaiverService $waiverService,
    ) {}

    public function index(Card $card): JsonResponse
    {
        $this->authorize('view', $card);

        $statements = $card->statements()->with('transactions')->orderByDesc('billing_year')->orderByDesc('billing_month')->get();

        return response()->json(StatementResource::collection($statements));
    }

    public function store(StoreStatementRequest $request, Card $card): JsonResponse
    {
        $this->authorize('update', $card);

        $file = $request->file('file');
        $result = $this->statementAnalysisService->analyze($file);

        $path = $file->store("statements/{$card->id}", 'local');

        $request->attributes->set('rollback_files', [$path]);
        $statement = $card->statements()->create([
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'billing_month' => $request->validated('billing_month'),
            'billing_year' => $request->validated('billing_year'),
            'analysis_status' => $result['analyzed'] ? 'completed' : 'failed',
            'analysis_message' => $result['message'],
            'statement_date' => $result['statement']['statement_date'] ?? null,
            'due_date' => $result['statement']['due_date'] ?? null,
            'total_due' => $result['statement']['total_due'] ?? null,
            'minimum_due' => $result['statement']['minimum_due'] ?? null,
        ]);

        $periods = [];

        foreach ($result['transactions'] as $transaction) {
            $date = $this->resolveTransactionDate($transaction['date'], $statement);

            $card->transactions()->create([
                'statement_id' => $statement->id,
                'transaction_date' => $date,
                'description' => $transaction['description'],
                'amount' => $transaction['amount'],
                'category' => $transaction['category'],
            ]);

            $periods[$date->format('Y-m')] = ['year' => (int) $date->format('Y'), 'month' => (int) $date->format('n')];
        }

        if ($periods !== []) {
            $this->recomputeSpendForPeriods($card, $periods);
        }

        $cardUpdates = [];
        if (($result['statement']['total_due'] ?? null) !== null) {
            $cardUpdates['current_outstanding'] = $result['statement']['total_due'];
        }
        if (($result['statement']['reward_point_balance'] ?? null) !== null) {
            $cardUpdates['reward_point_balance'] = $result['statement']['reward_point_balance'];
        }
        if ($cardUpdates !== []) {
            $card->update($cardUpdates);
        }

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

        $path = $statement->file_path;
        \Illuminate\Support\Facades\DB::afterCommit(fn () => Storage::disk('local')->delete($path));
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

    private function resolveTransactionDate(?string $date, Statement $statement): \Carbon\Carbon
    {
        if ($date !== null) {
            try {
                return \Carbon\Carbon::parse($date);
            } catch (\Throwable) {
                // fall through to billing-period fallback
            }
        }

        return \Carbon\Carbon::create($statement->billing_year, $statement->billing_month, 1);
    }

    /**
     * Recompute MonthlySpendEntry rows for the given (year, month) periods
     * as the full sum of this card's Transaction rows in that period,
     * grouped by category — transactions are the source of truth once they
     * exist, so this stays correct (idempotent) across re-uploads and
     * overlapping statement cycles. Categories with no remaining
     * transactions for the period are zeroed rather than left stale.
     *
     * @param iterable<array{year: int, month: int}> $periods
     */
    private function recomputeSpendForPeriods(Card $card, array $periods): void
    {
        app(\App\Services\SpendAggregationService::class)->recompute($card, $periods);
    }
}
