<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BenefitController;
use App\Http\Controllers\Api\CardAnalysisController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\CardImportController;
use App\Http\Controllers\Api\ComparisonController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MonthlySpendEntryController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SpendAnalyzerController;
use App\Http\Controllers\Api\StatementController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware(['auth:api', \App\Http\Middleware\ActiveAccount::class, \App\Http\Middleware\TransactionalWrites::class])->group(function () {
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:api', \App\Http\Middleware\ActiveAccount::class, \App\Http\Middleware\TransactionalWrites::class])->group(function () {
    Route::post('cards/import', [CardImportController::class, 'store']);
    Route::post('cards/analyze', [CardAnalysisController::class, 'store']);
    Route::apiResource('cards', CardController::class);
    Route::apiResource('cards.benefits', BenefitController::class)->shallow();
    Route::post('benefits/{benefit}/mark-used', [BenefitController::class, 'markUsed']);
    Route::apiResource('cards.statements', StatementController::class)->shallow()->except(['update']);
    Route::get('statements/{statement}/download', [StatementController::class, 'download']);
    Route::get('recommendation', [RecommendationController::class, 'index']);
    Route::get('recommendation/monthly-plan', [RecommendationController::class, 'monthlyPlan']);
    Route::get('comparison', [ComparisonController::class, 'index']);
    Route::get('cards/{card}/spend-entries', [MonthlySpendEntryController::class, 'index']);
    Route::post('cards/{card}/spend-entries', [MonthlySpendEntryController::class, 'store']);
    Route::get('reports/monthly', [ReportController::class, 'monthly']);
    Route::get('reports/spend-by-category', [SpendAnalyzerController::class, 'index']);
    Route::get('dashboard', [DashboardController::class, 'index']);
});

Route::middleware(['auth:api', \App\Http\Middleware\ActiveAccount::class, \App\Http\Middleware\TransactionalWrites::class])->group(function () {
 Route::get('admin/users',[\App\Http\Controllers\Api\AdminController::class,'users']);
 Route::patch('admin/users/{user}/status',[\App\Http\Controllers\Api\AdminController::class,'status']);
 Route::get('admin/health',[\App\Http\Controllers\Api\AdminController::class,'health']);
 Route::get('admin/audit',[\App\Http\Controllers\Api\AdminController::class,'audit']);
 Route::get('cards/{card}/sharing',[\App\Http\Controllers\Api\SharingController::class,'index']);
 Route::post('cards/{card}/sharing',[\App\Http\Controllers\Api\SharingController::class,'invite']);
 Route::patch('cards/{card}/sharing/{member}',[\App\Http\Controllers\Api\SharingController::class,'update']);
 Route::delete('cards/{card}/sharing/{member}',[\App\Http\Controllers\Api\SharingController::class,'destroy']);
 Route::get('invitations',[\App\Http\Controllers\Api\SharingController::class,'invitations']);
 Route::post('invitations/{invitation}/accept',[\App\Http\Controllers\Api\SharingController::class,'accept']);
});

Route::middleware(['auth:api',\App\Http\Middleware\ActiveAccount::class])->group(function() {
 Route::get('sync/bootstrap',[\App\Http\Controllers\Api\SyncController::class,'bootstrap']);
 Route::get('sync/changes',[\App\Http\Controllers\Api\SyncController::class,'changes']);
 Route::post('sync/mutations',[\App\Http\Controllers\Api\SyncController::class,'mutate']);
});

Route::middleware(['auth:api',\App\Http\Middleware\ActiveAccount::class,\App\Http\Middleware\TransactionalWrites::class])->group(function() {
 Route::post('cards/{card}/messages/preview',[\App\Http\Controllers\Api\MessageImportController::class,'preview']);
 Route::post('cards/{card}/messages/confirm',[\App\Http\Controllers\Api\MessageImportController::class,'confirm']);
});

Route::post('recommendation/explain',\App\Http\Controllers\Api\RecommendationExplanationController::class)->middleware(['auth:api',\App\Http\Middleware\ActiveAccount::class,'throttle:6,1']);
