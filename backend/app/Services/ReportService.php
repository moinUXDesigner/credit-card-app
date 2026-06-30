<?php

namespace App\Services;

use App\Http\Resources\BenefitResource;
use App\Models\User;
use Carbon\Carbon;

class ReportService
{
    public function __construct(
        private UtilizationService $utilizationService,
        private WaiverService $waiverService,
    ) {}

    public function monthly(User $user, int $year, int $month): array
    {
        $cards = $user->cards()
            ->with(['benefits', 'spendEntries' => function ($query) use ($year, $month) {
                $query->where('year', $year)->where('month', $month);
            }])
            ->get();

        $totalSpend = $cards->sum(fn ($card) => (float) ($card->spendEntries->first()?->amount_spent ?? 0));

        $bestCard = $cards->sortByDesc(fn ($card) => (float) ($card->spendEntries->first()?->amount_spent ?? 0))->first();
        $bestCardUsed = $bestCard && ($bestCard->spendEntries->first()?->amount_spent ?? 0) > 0
            ? ['id' => $bestCard->id, 'card_name' => $bestCard->card_name]
            : null;

        $waiverProgress = $cards->map(fn ($card) => [
            'card_id' => $card->id,
            'card_name' => $card->card_name,
            'waiver_progress_percentage' => $this->waiverService->progressPercentage($card),
        ])->values();

        $utilizationStatus = $cards->map(function ($card) {
            $pct = $this->utilizationService->cardUtilization($card);

            return [
                'card_id' => $card->id,
                'card_name' => $card->card_name,
                'utilization_percentage' => $pct,
                'band' => $this->utilizationService->band($pct),
            ];
        })->values();

        $periodEnd = Carbon::create($year, $month, 1)->endOfMonth();
        $unusedBenefits = $cards->flatMap(fn ($card) => $card->benefits)
            ->filter(fn ($benefit) => $benefit->used_count < $benefit->total_allowed)
            ->values();

        $potentialMissedRewards = $unusedBenefits
            ->filter(fn ($benefit) => $benefit->expiry_date && $benefit->expiry_date->lessThanOrEqualTo($periodEnd))
            ->sum(fn ($benefit) => (float) ($benefit->estimated_value ?? 0));

        return [
            'year' => $year,
            'month' => $month,
            'total_spend' => round($totalSpend, 2),
            'best_card_used' => $bestCardUsed,
            'waiver_progress' => $waiverProgress,
            'utilization_status' => $utilizationStatus,
            'unused_benefits' => BenefitResource::collection($unusedBenefits),
            'potential_missed_rewards_value' => round($potentialMissedRewards, 2),
        ];
    }
}
