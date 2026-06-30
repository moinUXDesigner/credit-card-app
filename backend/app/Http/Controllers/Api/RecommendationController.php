<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function __construct(private RecommendationService $recommendationService) {}

    public function index(Request $request): JsonResponse
    {
        $cards = $request->user()->cards()->with('benefits')->where('is_active', true)->get();

        $category = $request->query('category');

        return response()->json($this->recommendationService->recommend($cards, $category));
    }
}
