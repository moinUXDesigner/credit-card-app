<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportCardsRequest;
use App\Services\CardImportService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class CardImportController extends Controller
{
    public function __construct(private CardImportService $cardImportService) {}

    public function store(ImportCardsRequest $request): JsonResponse
    {
        try {
            $result = $this->cardImportService->import($request->user(), $request->file('file'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result, 201);
    }
}
