<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Support\Collection;

class UtilizationService
{
    public function cardUtilization(Card $card): float
    {
        if ($card->total_limit <= 0) {
            return 0.0;
        }

        return round(($card->current_outstanding / $card->total_limit) * 100, 2);
    }

    public function overallUtilization(Collection $cards): float
    {
        $totalLimit = $cards->sum('total_limit');
        $totalOutstanding = $cards->sum('current_outstanding');

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
