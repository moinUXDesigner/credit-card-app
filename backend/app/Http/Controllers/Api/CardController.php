<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCardRequest;
use App\Http\Requests\UpdateCardRequest;
use App\Http\Resources\CardResource;
use App\Models\Card;
use App\Models\StatementPreview;
use App\Services\StatementImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cards = $request->user()->accessibleCards()->orderBy('card_name')->get();

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

        $card->fill($request->validated());
        $summaryChanges = $card->getDirty();
        if ($card->isDirty('shared_limit_group')) {
            $summaryChanges['total_limit'] = $card->total_limit;
        }
        app(StatementImportService::class)->markExternal($card, $summaryChanges, 'manual');
        $card->save();

        return response()->json(new CardResource($card));
    }

    public function destroy(Card $card): JsonResponse
    {
        $this->authorize('delete', $card);

        $paths = $card->statements()->pluck('file_path')->merge(StatementPreview::where('card_id', $card->id)->pluck('file_path'))->unique()->all();
        DB::afterCommit(fn () => Storage::disk('local')->delete($paths));
        $card->delete();

        return response()->json(null, 204);
    }
}
