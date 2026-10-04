<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Card;
use App\Models\Statement;
use App\Services\SpendAggregationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionCategoryController extends Controller
{
    public function update(Request $request, Statement $statement, int $transaction): JsonResponse
    {
        $this->authorize('update', $statement);
        $data = $request->validate(['category' => ['required', Rule::in(Card::CATEGORIES)]]);
        $row = $statement->transactions()->whereKey($transaction)->lockForUpdate()->firstOrFail();
        $row->update(['category' => $data['category']]);
        if ($row->transaction_date) {
            app(SpendAggregationService::class)->recompute($statement->card, [
                ['year' => $row->transaction_date->year, 'month' => $row->transaction_date->month],
            ]);
        }

        return response()->json(new TransactionResource($row));
    }
}
