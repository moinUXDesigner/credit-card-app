<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Support\Collection;

class UtilizationService
{
    /**
     * Utilization against a card's own limit. When the card shares a pooled
     * limit with sibling cards (`shared_limit_group`), the numerator is the
     * combined outstanding balance across the whole group, since the credit
     * line is one shared pool rather than one per card.
     */
    public function cardUtilization(Card $card): float
    {
        if ($card->total_limit <= 0) {
            return 0.0;
        }

        $groupOutstanding = $card->limitGroupCards()->sum(fn (Card $c) => (float) $c->current_outstanding);

        return round(($groupOutstanding / (float) $card->total_limit) * 100, 2);
    }

    /**
     * Overall utilization across all of a user's cards. Cards sharing a
     * `shared_limit_group` pool one physical credit line, so that limit must
     * only be counted once (not once per card) when summing total limit.
     */
    public function overallUtilization(Collection $cards): float
    {
        $totalOutstanding = $cards->sum(fn (Card $c) => (float) $c->current_outstanding);

        $totalLimit = $cards
            ->unique(fn (Card $c) => $c->shared_limit_group ? $c->user_id.':'.$c->shared_limit_group : 'card-'.($c->id ?? spl_object_id($c)))
            ->sum(fn (Card $c) => (float) $c->total_limit);

        if ($totalLimit <= 0) {
            return 0.0;
        }

        return round(($totalOutstanding / $totalLimit) * 100, 2);
    }

    public function band(float $utilizationPct): string
    {
        return match (true) {
            $utilizationPct < 30 => 'good',
            $utilizationPct < 50 => 'caution',
            $utilizationPct < 75 => 'avoid_further_use',
            default => 'urgent_repayment',
        };
    }

    public function bandMessage(string $band): string
    {
        return match ($band) {
            'good' => 'Healthy utilization — supports healthier credit card usage.',
            'caution' => 'Utilization rising — consider slowing spend on this card.',
            'avoid_further_use' => 'High utilization — avoid further use on this card until paid down.',
            'urgent_repayment' => 'Very high utilization — prioritize repayment on this card.',
            default => '',
        };
    }
}
