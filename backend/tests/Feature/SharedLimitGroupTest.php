<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedLimitGroupTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = auth('api')->login($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_cards_sharing_a_limit_group_report_combined_utilization(): void
    {
        $user = User::factory()->create();
        Card::factory()->for($user)->create([
            'total_limit' => 100000,
            'shared_limit_group' => 'hdfc-combined',
            'current_outstanding' => 30000,
        ]);
        $card2 = Card::factory()->for($user)->create([
            'total_limit' => 100000,
            'shared_limit_group' => 'hdfc-combined',
            'current_outstanding' => 20000,
        ]);

        // Each card's own utilization reflects the pooled balance (50000) against
        // the shared 100000 limit, not just its own outstanding balance.
        $response = $this->getJson("/api/cards/{$card2->id}", $this->authHeaders($user))->assertStatus(200);

        $this->assertEqualsWithDelta(50.0, $response->json('utilization_percentage'), 0.01);
    }

    public function test_dashboard_overall_utilization_does_not_double_count_shared_limit(): void
    {
        $user = User::factory()->create();
        Card::factory()->for($user)->create([
            'total_limit' => 100000,
            'shared_limit_group' => 'hdfc-combined',
            'current_outstanding' => 30000,
        ]);
        Card::factory()->for($user)->create([
            'total_limit' => 100000,
            'shared_limit_group' => 'hdfc-combined',
            'current_outstanding' => 20000,
        ]);

        // If the shared limit were double-counted, this would be 25% (50000/200000)
        // instead of the correct 50% (50000/100000).
        $response = $this->getJson('/api/dashboard', $this->authHeaders($user))->assertStatus(200);

        $this->assertEqualsWithDelta(50.0, $response->json('overall_utilization.percentage'), 0.01);
    }

    public function test_create_card_rejects_mismatched_limit_for_existing_group(): void
    {
        $user = User::factory()->create();
        Card::factory()->for($user)->create([
            'total_limit' => 100000,
            'shared_limit_group' => 'hdfc-combined',
        ]);

        $payload = [
            'card_name' => 'Regalia Gold',
            'bank_name' => 'HDFC',
            'last_four_digits' => '5678',
            'network' => 'visa',
            'total_limit' => 150000,
            'shared_limit_group' => 'hdfc-combined',
            'current_outstanding' => 0,
            'statement_day' => 5,
            'due_day' => 25,
            'annual_fee_amount' => 0,
            'annual_fee_month' => 9,
            'waiver_spend_required' => 0,
            'card_year_start_month' => 9,
        ];

        $this->postJson('/api/cards', $payload, $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['total_limit']);
    }

    public function test_update_card_rejects_mismatched_limit_for_existing_group(): void
    {
        $user = User::factory()->create();
        Card::factory()->for($user)->create([
            'total_limit' => 100000,
            'shared_limit_group' => 'hdfc-combined',
        ]);
        $card2 = Card::factory()->for($user)->create(['total_limit' => 150000]);

        $this->putJson("/api/cards/{$card2->id}", [
            'shared_limit_group' => 'hdfc-combined',
        ], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['total_limit']);
    }
}
