<?php

namespace App\Services;

use App\Models\Card;

class SpendAggregationService
{
    public function recompute(Card $card, array $periods): void
    {
        foreach ($periods as $period) {
            $entries = $card->spendEntries()->where('year', $period['year'])->where('month', $period['month'])->get();
            $sums = $card->transactions()->where('direction', 'purchase')->whereYear('transaction_date', $period['year'])->whereMonth('transaction_date', $period['month'])->get()->groupBy(fn ($t) => $t->category ?? 'other')->map(fn ($group) => $group->sum('amount'));
            foreach ($entries as $entry) {
                $entry->amount_spent = (float) $entry->manual_amount + (float) ($sums[$entry->category ?? ''] ?? 0);
                $entry->save();
                $sums->forget($entry->category ?? '');
            }
            foreach ($sums as $category => $amount) {
                $card->spendEntries()->create(['year' => $period['year'], 'month' => $period['month'], 'category' => $category, 'manual_amount' => 0, 'amount_spent' => $amount]);
            }
        }
        $card->update(['waiver_spend_completed' => app(WaiverService::class)->sumEntriesInCurrentCycle($card, $card->spendEntries()->get())]);
    }
}
