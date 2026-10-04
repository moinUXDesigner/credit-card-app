<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BenefitController;
use App\Http\Controllers\Api\CardAnalysisController;
use App\Http\Controllers\Api\CardChatController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\CardImportController;
use App\Http\Controllers\Api\ComparisonController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MessageImportController;
use App\Http\Controllers\Api\MonthlySpendEntryController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\RecommendationExplanationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SharingController;
use App\Http\Controllers\Api\SpendAnalyzerController;
use App\Http\Controllers\Api\StatementController;
use App\Http\Controllers\Api\StatementPreviewController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TransactionCategoryController;
use App\Http\Middleware\ActiveAccount;
use App\Http\Middleware\TransactionalWrites;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware(['auth:api', ActiveAccount::class, TransactionalWrites::class])->group(function () {
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:api', ActiveAccount::class, TransactionalWrites::class])->group(function () {
    Route::post('cards/import', [CardImportController::class, 'store']);
    Route::post('statement-previews', [StatementPreviewController::class, 'store'])->middleware('throttle:6,1');
    Route::get('statement-previews/{statementPreview}', [StatementPreviewController::class, 'show']);
    Route::post('cards/analyze', [CardAnalysisController::class, 'store'])->middleware('throttle:6,1');
    Route::get('chat/conversations', [CardChatController::class, 'index']);
    Route::post('chat/conversations', [CardChatController::class, 'store']);
    Route::get('chat/conversations/{conversation}', [CardChatController::class, 'show']);
    Route::delete('chat/conversations/{conversation}', [CardChatController::class, 'destroy']);
    Route::post('chat/conversations/{conversation}/messages', [CardChatController::class, 'message'])->middleware('throttle:6,1');
    Route::get('ai/requests/{aiRequest}', [CardChatController::class, 'requestStatus']);
    Route::apiResource('cards', CardController::class);
    Route::apiResource('cards.benefits', BenefitController::class)->shallow();
    Route::post('benefits/{benefit}/mark-used', [BenefitController::class, 'markUsed']);
    Route::apiResource('cards.statements', StatementController::class)->shallow()->except(['update']);
    Route::patch('statements/{statement}/transactions/{transaction}/category', [TransactionCategoryController::class, 'update']);
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

Route::middleware(['auth:api', ActiveAccount::class, TransactionalWrites::class])->group(function () {
    Route::get('admin/users', [AdminController::class, 'users']);
    Route::patch('admin/users/{user}/status', [AdminController::class, 'status']);
    Route::get('admin/health', [AdminController::class, 'health']);
    Route::get('admin/audit', [AdminController::class, 'audit']);
    Route::get('cards/{card}/sharing', [SharingController::class, 'index']);
    Route::post('cards/{card}/sharing', [SharingController::class, 'invite']);
    Route::patch('cards/{card}/sharing/{member}', [SharingController::class, 'update']);
    Route::delete('cards/{card}/sharing/{member}', [SharingController::class, 'destroy']);
    Route::get('invitations', [SharingController::class, 'invitations']);
    Route::post('invitations/{invitation}/accept', [SharingController::class, 'accept']);
});

Route::middleware(['auth:api', ActiveAccount::class])->group(function () {
    Route::get('sync/bootstrap', [SyncController::class, 'bootstrap']);
    Route::get('sync/changes', [SyncController::class, 'changes']);
    Route::post('sync/mutations', [SyncController::class, 'mutate']);
});

Route::middleware(['auth:api', ActiveAccount::class, TransactionalWrites::class])->group(function () {
    Route::post('cards/{card}/messages/preview', [MessageImportController::class, 'preview']);
    Route::post('cards/{card}/messages/confirm', [MessageImportController::class, 'confirm']);
});

Route::post('recommendation/explain', RecommendationExplanationController::class)->middleware(['auth:api', ActiveAccount::class, 'throttle:6,1']);
