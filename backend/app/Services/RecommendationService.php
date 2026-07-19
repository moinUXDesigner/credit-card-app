<?php

namespace App\Services;

use App\Models\Card;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RecommendationService
{
    public function __construct(
        private UtilizationService $utilizationService,
        private WaiverService $waiverService,
    ) {}

    public function scoreCard(Card $card, string|array|null $category = null, ?Carbon $now = null): array
    {
        $waiverUrgency = $this->waiverService->urgencyScore($card, $now);
        $rewardCategoryScore = $this->rewardCategoryScore($card, $category);
        $unusedBenefitScore = $this->unusedBenefitScore($card, $now);

        $utilizationPct = $this->utilizationService->cardUtilization($card);
        $utilizationPenalty = $this->utilizationPenalty($utilizationPct);
        $dueDateRiskPenalty = $this->dueDateRiskPenalty($card, $now);

        $totalScore = $waiverUrgency + $rewardCategoryScore + $unusedBenefitScore
            - $utilizationPenalty - $dueDateRiskPenalty;

        return [
            'card_id' => $card->id,
            'card_name' => $card->card_name,
            'total_score' => round($totalScore, 2),
            'breakdown' => [
                'waiver_urgency_score' => $waiverUrgency,
                'reward_category_score' => $rewardCategoryScore,
                'unused_benefit_score' => $unusedBenefitScore,
                'utilization_penalty' => $utilizationPenalty,
                'due_date_risk_penalty' => $dueDateRiskPenalty,
            ],
        ];
    }

    /**
     * When given multiple categories (e.g. this month's target spend areas),
     * a card is scored by its single best-matching category rather than the
     * sum, since covering one target category well already makes a card
     * worth using — it doesn't need to match all of them.
     */
    private function rewardCategoryScore(Card $card, string|array|null $category): float
    {
        $categories = array_filter((array) $category);
        if ($categories === []) {
            return 0.0;
        }

        $best = $card->best_categories ?? [];
        $generalScore = min(15.0, (float) ($card->reward_rate_general ?? 0) * 3);

        return max(array_map(
            fn (string $cat) => in_array($cat, $best, true) ? 30.0 : $generalScore,
            $categories,
        ));
    }

    private function unusedBenefitScore(Card $card, ?Carbon $now = null): float
    {
        $now ??= Carbon::now();
        $benefits = $card->benefits;

        if ($benefits->isEmpty()) {
            return 0.0;
        }

        $score = 0.0;
        foreach ($benefits as $benefit) {
            $remaining = max(0, $benefit->total_allowed - $benefit->used_count);
            if ($remaining <= 0) {
                continue;
            }

            $expiringSoon = $benefit->expiry_date
                && $now->diffInDays($benefit->expiry_date, false) >= 0
                && $now->diffInDays($benefit->expiry_date, false) <= 30;

            $score += $expiringSoon ? 8.0 : 4.0;
        }

        return round(min(20.0, $score), 2);
    }

    private function utilizationPenalty(float $utilizationPct): float
    {
        return match (true) {
            $utilizationPct < 30 => 0.0,
            $utilizationPct < 50 => 10.0,
            $utilizationPct < 75 => 25.0,
            default => 50.0,
        };
    }

    private function dueDateRiskPenalty(Card $card, ?Carbon $now = null): float
    {
        $daysUntilDue = $this->daysUntil($card->due_day, $now ?? Carbon::now());

        return match (true) {
            $daysUntilDue <= 3 => 20.0,
            $daysUntilDue <= 7 => 10.0,
            default => 0.0,
        };
    }

    private function daysUntil(int $dayOfMonth, Carbon $now): int
    {
        $target = Carbon::create($now->year, $now->month, min($dayOfMonth, $now->daysInMonth));
        if ($target->lessThan($now)) {
            $target->addMonthNoOverflow();
            $target->day(min($dayOfMonth, $target->daysInMonth));
        }

        return $now->diffInDays($target);
    }

    public function recommend(Collection $cards, string|array|null $category = null, ?Carbon $now = null): array
    {
        return $cards->map(fn (Card $card) => $this->scoreCard($card, $category, $now))
            ->sortByDesc('total_score')
            ->values()
            ->toArray();
    }

    /**
     * Top N cards to prioritize spend on this month, blending waiver urgency
     * with reward-category fit across the given target categories.
     */
    public function monthlyPlan(Collection $cards, array $categories, ?Carbon $now = null, int $topN = 2): array
    {
        return array_slice($this->recommend($cards, $categories, $now), 0, $topN);
    }
}
