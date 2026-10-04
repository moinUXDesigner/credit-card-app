<?php

namespace App\Services;

use App\Models\Card;
use App\Models\ChatConversation;
use App\Models\MonthlySpendEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;

class CardChatContextService
{
    public function cards(User $user, ChatConversation $conversation)
    {
        $query = app(ChatAccessService::class)->cards($user);
        if ($conversation->card_id) {
            $query->whereKey($conversation->card_id);
        }

        return $query->with('benefits')->get();
    }

    public function tool(string $name, array $arguments, $cards): array
    {
        $selected = isset($arguments['card_id']) ? $cards->where('id', $arguments['card_id']) : $cards;
        if (isset($arguments['card_id']) && $selected->isEmpty()) {
            throw new \RuntimeException('Card is outside this conversation.');
        }
        if ($name === 'read_cards') {
            return ['cards' => $selected->map(fn ($card) => [
                'source_id' => 'card:'.$card->id, 'card_id' => $card->id, 'name' => $card->card_name, 'bank' => $card->bank_name, 'last_four_digits' => $card->last_four_digits,
                'limit' => (float) $card->total_limit, 'outstanding' => (float) $card->current_outstanding, 'utilization_percentage' => $card->total_limit > 0 && $this->completeGroup($card, $cards) ? app(UtilizationService::class)->cardUtilization($card) : null,
                'statement_day' => $card->statement_day, 'due_day' => $card->due_day, 'next_statement' => app(DateOccurrenceService::class)->nextOccurrence($card->statement_day, now('Asia/Kolkata')), 'next_due' => app(DateOccurrenceService::class)->nextOccurrence($card->due_day, now('Asia/Kolkata')), 'reward_points' => (float) $card->reward_point_balance,
                'annual_fee' => (float) $card->annual_fee_amount, 'waiver_target' => (float) $card->waiver_spend_required, 'waiver_progress' => (float) $card->waiver_spend_completed,
                'benefits' => $card->benefits->map(fn ($b) => $b->only(['type', 'title', 'frequency', 'total_allowed', 'used_count', 'expiry_date', 'estimated_value'])),
            ])->values()->all()];
        }
        if ($name === 'recommendations') {
            $category = $arguments['category'] ?? null;
            if ($category !== null && ! in_array($category, Card::CATEGORIES, true)) {
                throw new \RuntimeException('Invalid category.');
            }

            return ['ranking' => app(RecommendationService::class)->recommend($selected->filter(fn ($card) => $card->is_active && $this->completeGroup($card, $cards)), $category), 'note' => 'Ranking is authoritative. Explain the supplied order. Inactive cards and shared-limit cards whose whole group is outside the accessible scope are excluded.'];
        }
        $from = $arguments['from'] ?? now()->startOfMonth()->toDateString();
        $to = $arguments['to'] ?? now()->toDateString();
        foreach ([$from, $to] as $date) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! \DateTimeImmutable::createFromFormat('!Y-m-d', $date) || \DateTimeImmutable::createFromFormat('!Y-m-d', $date)->format('Y-m-d') !== $date) {
                throw new \RuntimeException('Invalid date.');
            }
        }
        if ($from > $to || Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 366) {
            throw new \RuntimeException('Use a date range of at most one year.');
        }
        $query = Transaction::whereIn('card_id', $selected->pluck('id'))->whereBetween('transaction_date', [$from, $to]);
        if ($name === 'transactions') {
            return ['from' => $from, 'to' => $to, 'coverage' => 'Up to 200 saved transactions; pending PDFs are excluded.', 'transactions' => $query->orderByDesc('transaction_date')->limit(200)->get()->map(fn ($t) => ['source_id' => 'statement:'.$t->statement_id, 'date' => $t->transaction_date?->toDateString(), 'description' => $t->description, 'amount' => (float) $t->amount, 'direction' => $t->direction, 'category' => $t->category, 'card_id' => $t->card_id])->all()];
        }
        if ($name === 'spending') {
            $purchases = (clone $query)->where('direction', 'purchase')->selectRaw('category, SUM(amount) as total')->groupBy('category')->get()->map(fn ($row) => ['category' => $row->category ?? 'other', 'amount' => (float) $row->total]);
            $manual = MonthlySpendEntry::whereIn('card_id', $selected->pluck('id'))->get()->filter(fn ($e) => sprintf('%04d-%02d', $e->year, $e->month) >= substr($from, 0, 7) && sprintf('%04d-%02d', $e->year, $e->month) <= substr($to, 0, 7))->groupBy('category')->map(fn ($rows) => (float) $rows->sum('manual_amount'));

            return ['from' => $from, 'to' => $to, 'purchases' => $purchases, 'purchase_total' => (float) (clone $query)->where('direction', 'purchase')->sum('amount'), 'credits_total' => (float) (clone $query)->where('direction', 'credit')->sum('amount'), 'manual_monthly_amounts' => $manual, 'manual_coverage' => 'Manual entries cover whole selected months, not exact day ranges. Do not double-count imported totals.'];
        }
        throw new \RuntimeException('Unsupported tool.');
    }

    private function completeGroup(Card $card, $cards): bool
    {
        return ! $card->shared_limit_group || $card->limitGroupCards()->pluck('id')->diff($cards->pluck('id'))->isEmpty();
    }
}
