<?php

namespace Database\Factories;

use App\Models\Benefit;
use App\Models\Card;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Benefit>
 */
class BenefitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'type' => fake()->randomElement(['lounge', 'cashback', 'reward_points', 'dining', 'movie', 'other']),
            'title' => fake()->randomElement(['Airport lounge access', 'Monthly cashback cap', 'Movie ticket offer']),
            'frequency' => fake()->randomElement(['monthly', 'quarterly', 'yearly', 'one_time']),
            'total_allowed' => fake()->numberBetween(1, 8),
            'used_count' => 0,
            'expiry_date' => null,
            'estimated_value' => fake()->randomFloat(2, 100, 2000),
            'cycle_start_date' => null,
        ];
    }
}
