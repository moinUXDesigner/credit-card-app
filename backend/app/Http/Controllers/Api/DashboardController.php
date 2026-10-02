<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BenefitResource;
use App\Services\DateOccurrenceService;
use App\Services\UtilizationService;
use App\Services\WaiverService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const UPCOMING_DAYS_WINDOW = 14;

    public function __construct(
        private UtilizationService $utilizationService,
        private WaiverService $waiverService,
        private DateOccurrenceService $dateOccurrenceService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $cards = $request->user()->accessibleCards()->with('benefits')->where('is_active', true)->get();

        $overallPct = $this->utilizationService->overallUtilization($cards);

        $upcomingDates = $cards->flatMap(function ($card) use ($now) {
            return [
                $this->describeOccurrence($card, 'due', $card->due_day, $now),
                $this->describeOccurrence($card, 'statement', $card->statement_day, $now),
            ];
        })
            ->filter(fn ($item) => $item['days_until'] <= self::UPCOMING_DAYS_WINDOW)
            ->sortBy('days_until')
            ->values();

        $waiverAlerts = $cards
            ->map(fn ($card) => [
                'card_id' => $card->id,
                'card_name' => $card->card_name,
                'remaining_spend' => $this->waiverService->remainingSpend($card),
                'months_left' => $this->waiverService->monthsLeft($card, $now),
                'suggested_monthly_spend' => $this->waiverService->suggestedMonthlySpend($card, $now),
            ])
            ->filter(fn ($alert) => $alert['remaining_spend'] > 0)
            ->values();

        $unusedBenefits = $cards->flatMap(fn ($card) => $card->benefits)
            ->filter(fn ($benefit) => $benefit->used_count < $benefit->total_allowed)
            ->values();

        return response()->json([
            'overall_utilization' => [
                'percentage' => $overallPct,
                'band' => $this->utilizationService->band($overallPct),
            ],
            'upcoming_dates' => $upcomingDates,
            'waiver_alerts' => $waiverAlerts,
            'unused_benefits' => BenefitResource::collection($unusedBenefits),
        ]);
    }

    private function describeOccurrence($card, string $type, int $dayOfMonth, Carbon $now): array
    {
        $occurrence = $this->dateOccurrenceService->nextOccurrence($dayOfMonth, $now);

        return [
            'card_id' => $card->id,
            'card_name' => $card->card_name,
            'type' => $type,
            ...$occurrence,
        ];
    }
}
