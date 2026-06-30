<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CardResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComparisonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cardIds = (array) $request->query('card_ids', []);

        $cards = $request->user()->cards()
            ->when(! empty($cardIds), fn ($query) => $query->whereIn('id', $cardIds))
            ->get();

        return response()->json(CardResource::collection($cards));
    }
}
