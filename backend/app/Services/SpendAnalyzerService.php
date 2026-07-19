<?php

namespace App\Services;

use App\Models\MonthlySpendEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SpendAnalyzerService
{
    /**
     * Category-wise spend breakdown across all of the user's cards for the
     * trailing $months (including the current month), with each category's
     * share of the period total.
     */
    public function spendByCategory(User $user, int $months, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();

        $toKey = $now->year * 12 + $now->month;
        $fromKey = $toKey - ($months - 1);

        $cardIds = $user->cards()->pluck('id');

        $rows = MonthlySpendEntry::query()
            ->whereIn('card_id', $cardIds)
            ->whereBetween(DB::raw('(year * 12 + month)'), [$fromKey, $toKey])
            ->selectRaw('category, SUM(amount_spent) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $total = (float) $rows->sum('total');

        $categories = $rows->map(fn ($row) => [
            'category' => $row->category ?? 'uncategorized',
            'amount' => round((float) $row->total, 2),
            'percentage' => $total > 0 ? round(((float) $row->total / $total) * 100, 2) : 0.0,
        ])->values()->all();

        return [
            'months' => $months,
            'from' => $this->keyToYearMonth($fromKey),
            'to' => $this->keyToYearMonth($toKey),
            'total_spend' => round($total, 2),
            'categories' => $categories,
        ];
    }

    private function keyToYearMonth(int $key): array
    {
        return [
            'year' => intdiv($key - 1, 12),
            'month' => (($key - 1) % 12) + 1,
        ];
    }
}
