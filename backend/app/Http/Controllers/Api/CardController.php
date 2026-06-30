<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCardRequest;
use App\Http\Requests\UpdateCardRequest;
use App\Http\Resources\CardResource;
use App\Models\Card;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cards = $request->user()->cards()->orderBy('card_name')->get();

        return response()->json(CardResource::collection($cards));
    }

    public function store(StoreCardRequest $request): JsonResponse
    {
        $card = $request->user()->cards()->create($request->validated());

        return response()->json(new CardResource($card), 201);
    }

    public function show(Card $card): JsonResponse
    {
        $this->authorize('view', $card);

        return response()->json(new CardResource($card));
    }

    public function update(UpdateCardRequest $request, Card $card): JsonResponse
    {
        $this->authorize('update', $card);

        $card->update($request->validated());

        return response()->json(new CardResource($card));
    }

    public function destroy(Card $card): JsonResponse
    {
        $this->authorize('delete', $card);

        $card->delete();

        return response()->json(null, 204);
    }
}
