<?php

namespace App\Services;

use App\Models\Card;

/**
 * Shared validation for card-prefill data coming from any extraction
 * source (AI analysis, PDF text-extraction fallback, …), so every path
 * produces identically-shaped, identically-validated output.
 */
class CardFieldSanitizer
{
    public const VALID_NETWORKS = ['visa', 'mastercard', 'rupay', 'amex'];

    public const VALID_BENEFIT_TYPES = ['lounge', 'cashback', 'reward_points', 'dining', 'movie', 'other'];

    public const VALID_BENEFIT_FREQUENCIES = ['monthly', 'quarterly', 'yearly', 'one_time'];

    /**
     * Defense-in-depth beyond any upstream schema: day/month need range
     * checks, last_four_digits must be exactly 4 digits, and enum-like
     * fields are re-checked against the canonical lists.
     */
    public function sanitizeCard(array $data): array
    {
        $statementDay = $this->validRange($data['statement_day'] ?? null, 1, 31);
        $dueDay = $this->validRange($data['due_day'] ?? null, 1, 31);
        $annualFeeMonth = $this->validRange($data['annual_fee_month'] ?? null, 1, 12);

        $lastFour = $data['last_four_digits'] ?? null;
        $lastFour = is_string($lastFour) && preg_match('/^\d{4}$/', $lastFour) ? $lastFour : null;

        $network = $data['network'] ?? null;
        $network = in_array($network, self::VALID_NETWORKS, true) ? $network : null;

        $bestCategories = array_values(array_intersect($data['best_categories'] ?? [], Card::CATEGORIES));

        return [
            'card_name' => $data['card_name'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'last_four_digits' => $lastFour,
            'network' => $network,
            'total_limit' => $data['total_limit'] ?? null,
            'statement_day' => $statementDay,
            'due_day' => $dueDay,
            'annual_fee_amount' => $data['annual_fee_amount'] ?? null,
            'annual_fee_month' => $annualFeeMonth,
            'waiver_spend_required' => $data['waiver_spend_required'] ?? null,
            'reward_point_balance' => $data['reward_point_balance'] ?? null,
            'reward_rate_general' => $data['reward_rate_general'] ?? null,
            'cashback_cap_amount' => $data['cashback_cap_amount'] ?? null,
            'forex_markup_percent' => $data['forex_markup_percent'] ?? null,
            'fuel_surcharge_waiver_percent' => $data['fuel_surcharge_waiver_percent'] ?? null,
            'insurance_cover_amount' => $data['insurance_cover_amount'] ?? null,
            'lounge_access' => $data['lounge_access'] ?? null,
            'best_categories' => $bestCategories,
        ];
    }

    public function sanitizeSuggestedBenefits(array $benefits): array
    {
        return array_values(array_filter(
            $benefits,
            fn ($b) => is_array($b)
                && in_array($b['type'] ?? null, self::VALID_BENEFIT_TYPES, true)
                && in_array($b['frequency'] ?? null, self::VALID_BENEFIT_FREQUENCIES, true)
                && ! empty($b['title'])
        ));
    }

    private function validRange(mixed $value, int $min, int $max): ?int
    {
        return is_int($value) && $value >= $min && $value <= $max ? $value : null;
    }
}
