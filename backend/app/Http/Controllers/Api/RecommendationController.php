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

    public function monthlyPlan(Request $request): JsonResponse
    {
        $cards = $request->user()->cards()->with('benefits')->where('is_active', true)->get();

        $categories = array_values(array_filter((array) $request->query('categories', [])));
        $topN = (int) $request->query('top_n', 2);

        return response()->json($this->recommendationService->monthlyPlan($cards, $categories, null, $topN));
    }
}
