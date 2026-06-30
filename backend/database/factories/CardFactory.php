<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'card_name' => fake()->randomElement(['Regalia', 'Cashback', 'Amazon Pay', 'Millennia']),
            'bank_name' => fake()->randomElement(['HDFC', 'SBI', 'ICICI', 'Axis']),
            'last_four_digits' => fake()->numerify('####'),
            'network' => fake()->randomElement(['visa', 'mastercard', 'rupay', 'amex']),
            'total_limit' => fake()->randomFloat(2, 50000, 500000),
            'current_outstanding' => 0,
            'statement_day' => fake()->numberBetween(1, 28),
            'due_day' => fake()->numberBetween(1, 28),
            'annual_fee_amount' => fake()->randomFloat(2, 0, 5000),
            'annual_fee_month' => fake()->numberBetween(1, 12),
            'waiver_spend_required' => fake()->randomFloat(2, 0, 300000),
            'waiver_spend_completed' => 0,
            'card_year_start_month' => fake()->numberBetween(1, 12),
            'reward_point_balance' => 0,
            'reward_point_value_estimate' => 0.25,
            'best_categories' => [],
            'reward_rate_general' => fake()->randomFloat(2, 0.5, 5),
            'cashback_cap_amount' => null,
            'lounge_access' => fake()->boolean(),
            'is_active' => true,
        ];
    }
}
