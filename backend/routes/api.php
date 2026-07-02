<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BenefitController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\CardImportController;
use App\Http\Controllers\Api\ComparisonController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MonthlySpendEntryController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:api')->group(function () {
    Route::post('cards/import', [CardImportController::class, 'store']);
    Route::apiResource('cards', CardController::class);
    Route::apiResource('cards.benefits', BenefitController::class)->shallow();
    Route::post('benefits/{benefit}/mark-used', [BenefitController::class, 'markUsed']);
    Route::get('recommendation', [RecommendationController::class, 'index']);
    Route::get('comparison', [ComparisonController::class, 'index']);
    Route::get('cards/{card}/spend-entries', [MonthlySpendEntryController::class, 'index']);
    Route::post('cards/{card}/spend-entries', [MonthlySpendEntryController::class, 'store']);
    Route::get('reports/monthly', [ReportController::class, 'monthly']);
    Route::get('dashboard', [DashboardController::class, 'index']);
});
