<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SpendAnalyzerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SpendAnalyzerController extends Controller
{
    public function __construct(private SpendAnalyzerService $spendAnalyzerService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'months' => ['nullable', 'integer', Rule::in([1, 3, 6, 12])],
        ]);

        return response()->json(
            $this->spendAnalyzerService->spendByCategory($request->user(), $validated['months'] ?? 6)
        );
    }
}
