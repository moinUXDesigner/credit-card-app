<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CardAnalysisUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyzeCardRequest;
use App\Services\CardAnalysisService;
use Illuminate\Http\JsonResponse;

class CardAnalysisController extends Controller
{
    public function __construct(private CardAnalysisService $cardAnalysisService) {}

    public function store(AnalyzeCardRequest $request): JsonResponse
    {
        try {
            $result = $this->cardAnalysisService->analyze($request->file('file'));
        } catch (CardAnalysisUnavailableException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json($result);
    }
}
