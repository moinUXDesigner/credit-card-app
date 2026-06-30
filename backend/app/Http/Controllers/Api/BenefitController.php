<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBenefitRequest;
use App\Http\Requests\UpdateBenefitRequest;
use App\Http\Resources\BenefitResource;
use App\Models\Benefit;
use App\Models\Card;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class BenefitController extends Controller
{
    public function index(Card $card): JsonResponse
    {
        $this->authorize('view', $card);

        return response()->json(BenefitResource::collection($card->benefits()->get()));
    }

    public function store(StoreBenefitRequest $request, Card $card): JsonResponse
    {
        $this->authorize('update', $card);

        $benefit = $card->benefits()->create($request->validated());

        return response()->json(new BenefitResource($benefit), 201);
    }

    public function show(Benefit $benefit): JsonResponse
    {
        $this->authorize('view', $benefit);

        return response()->json(new BenefitResource($benefit));
    }

    public function update(UpdateBenefitRequest $request, Benefit $benefit): JsonResponse
    {
        $this->authorize('update', $benefit);

        $benefit->update($request->validated());

        return response()->json(new BenefitResource($benefit));
    }

    public function destroy(Benefit $benefit): JsonResponse
    {
        $this->authorize('delete', $benefit);

        $benefit->delete();

        return response()->json(null, 204);
    }

    public function markUsed(Benefit $benefit): JsonResponse
    {
        $this->authorize('update', $benefit);

        $benefit->usageLogs()->create(['used_on' => Carbon::today(), 'quantity' => 1]);
        $benefit->increment('used_count');

        return response()->json(new BenefitResource($benefit->refresh()));
    }
}
