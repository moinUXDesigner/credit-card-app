<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMonthlySpendEntryRequest;
use App\Http\Resources\MonthlySpendEntryResource;
use App\Models\Card;
use Illuminate\Http\JsonResponse;

class MonthlySpendEntryController extends Controller
{
    public function index(Card $card): JsonResponse
    {
        $this->authorize('view', $card);

        return response()->json(
            MonthlySpendEntryResource::collection($card->spendEntries()->orderByDesc('year')->orderByDesc('month')->get())
        );
    }

    public function store(StoreMonthlySpendEntryRequest $request, Card $card): JsonResponse
    {
        $this->authorize('update', $card);

        $validated = $request->validated();

        $entry = $card->spendEntries()->updateOrCreate(
            ['year' => $validated['year'], 'month' => $validated['month']],
            ['amount_spent' => $validated['amount_spent'], 'category' => $validated['category'] ?? null],
        );

        return response()->json(new MonthlySpendEntryResource($entry), 201);
    }
}
