<?php

namespace App\Http\Resources;

use App\Services\UtilizationService;
use App\Services\WaiverService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $utilizationService = app(UtilizationService::class);
        $waiverService = app(WaiverService::class);

        $utilizationPct = $utilizationService->cardUtilization($this->resource);
        $band = $utilizationService->band($utilizationPct);

        return [
            'id' => $this->id,
            'card_name' => $this->card_name,
            'bank_name' => $this->bank_name,
            'last_four_digits' => $this->last_four_digits,
            'network' => $this->network,
            'total_limit' => (float) $this->total_limit,
            'shared_limit_group' => $this->shared_limit_group,
            'current_outstanding' => (float) $this->current_outstanding,
            'statement_day' => $this->statement_day,
            'due_day' => $this->due_day,
            'annual_fee_amount' => (float) $this->annual_fee_amount,
            'annual_fee_month' => $this->annual_fee_month,
            'waiver_spend_required' => (float) $this->waiver_spend_required,
            'waiver_spend_completed' => (float) $this->waiver_spend_completed,
            'card_year_start_month' => $this->card_year_start_month,
            'reward_point_balance' => (float) $this->reward_point_balance,
            'reward_point_value_estimate' => (float) $this->reward_point_value_estimate,
            'best_categories' => $this->best_categories ?? [],
            'reward_rate_general' => $this->reward_rate_general !== null ? (float) $this->reward_rate_general : null,
            'cashback_cap_amount' => $this->cashback_cap_amount !== null ? (float) $this->cashback_cap_amount : null,
            'forex_markup_percent' => $this->forex_markup_percent !== null ? (float) $this->forex_markup_percent : null,
            'fuel_surcharge_waiver_percent' => $this->fuel_surcharge_waiver_percent !== null ? (float) $this->fuel_surcharge_waiver_percent : null,
            'insurance_cover_amount' => $this->insurance_cover_amount !== null ? (float) $this->insurance_cover_amount : null,
            'lounge_access' => (bool) $this->lounge_access,
            'is_active' => (bool) $this->is_active,
            'utilization_percentage' => $utilizationPct,
            'utilization_band' => $band,
            'utilization_message' => $utilizationService->bandMessage($band),
            'waiver_remaining_spend' => $waiverService->remainingSpend($this->resource),
            'waiver_progress_percentage' => $waiverService->progressPercentage($this->resource),
            'waiver_months_left' => $waiverService->monthsLeft($this->resource),
            'waiver_suggested_monthly_spend' => $waiverService->suggestedMonthlySpend($this->resource),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
