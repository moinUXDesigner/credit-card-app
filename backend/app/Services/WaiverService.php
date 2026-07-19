<?php

namespace App\Services;

use App\Models\Card;
use Carbon\Carbon;

class WaiverService
{
    public function remainingSpend(Card $card): float
    {
        return max(0, $card->waiver_spend_required - $card->waiver_spend_completed);
    }

    public function progressPercentage(Card $card): float
    {
        if ($card->waiver_spend_required <= 0) {
            return 100.0;
        }

        return round(min(100, ($card->waiver_spend_completed / $card->waiver_spend_required) * 100), 2);
    }

    /**
     * Months remaining until the card's fee-year anniversary
     * (card_year_start_month), inclusive of the current month.
     */
    public function monthsLeft(Card $card, ?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $cycleAnniversaryMonth = $card->card_year_start_month;

        $nextAnniversary = Carbon::create($now->year, $cycleAnniversaryMonth, 1);
        if ($nextAnniversary->lessThan($now->copy()->startOfMonth())) {
            $nextAnniversary->addYear();
        }

        return max(1, $now->copy()->startOfMonth()->diffInMonths($nextAnniversary) + 1);
    }

    public function suggestedMonthlySpend(Card $card, ?Carbon $now = null): float
    {
        $remaining = $this->remainingSpend($card);
        if ($remaining <= 0) {
            return 0.0;
        }

        return round($remaining / $this->monthsLeft($card, $now), 2);
    }

    /**
     * Start of the card's current fee-year cycle (the most recent occurrence
     * of card_year_start_month at or before now).
     */
    public function cycleStart(Card $card, ?Carbon $now = null): Carbon
    {
        $now ??= Carbon::now();

        $cycleStart = Carbon::create($now->year, $card->card_year_start_month, 1);
        if ($cycleStart->greaterThan($now->copy()->startOfMonth())) {
            $cycleStart->subYear();
        }

        return $cycleStart;
    }

    /**
     * Sums amount_spent across MonthlySpendEntry-like items (objects with
     * `year`/`month`/`amount_spent`) that fall within the card's current
     * 12-month fee-year cycle.
     */
    public function sumEntriesInCurrentCycle(Card $card, iterable $entries, ?Carbon $now = null): float
    {
        $cycleStart = $this->cycleStart($card, $now);
        $cycleEnd = $cycleStart->copy()->addMonths(11);

        $startKey = $cycleStart->year * 12 + $cycleStart->month;
        $endKey = $cycleEnd->year * 12 + $cycleEnd->month;

        $total = 0.0;
        foreach ($entries as $entry) {
            $key = $entry->year * 12 + $entry->month;
            if ($key >= $startKey && $key <= $endKey) {
                $total += (float) $entry->amount_spent;
            }
        }

        return round($total, 2);
    }

    /**
     * Urgency score 0-100 for the recommendation engine: higher means
     * more urgent to spend on this card to hit the waiver target in time.
     */
    public function urgencyScore(Card $card, ?Carbon $now = null): float
    {
        $remaining = $this->remainingSpend($card);
        if ($remaining <= 0) {
            return 0.0;
        }

        $monthsLeft = $this->monthsLeft($card, $now);
        $pctRemaining = $card->waiver_spend_required > 0
            ? ($remaining / $card->waiver_spend_required) * 100
            : 0;

        return round(min(100, $pctRemaining * (1 / $monthsLeft) * 3), 2);
    }
}
