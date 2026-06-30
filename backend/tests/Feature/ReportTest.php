<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Card;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = auth('api')->login($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_user_can_log_monthly_spend_for_own_card(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create();

        $this->postJson("/api/cards/{$card->id}/spend-entries", [
            'year' => 2026,
            'month' => 6,
            'amount_spent' => 25000,
        ], $this->authHeaders($user))
            ->assertStatus(201)
            ->assertJsonPath('amount_spent', 25000);

        $this->assertDatabaseHas('monthly_spend_entries', ['card_id' => $card->id, 'year' => 2026, 'month' => 6]);
    }

    public function test_logging_spend_twice_for_same_month_upserts(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create();

        $this->postJson("/api/cards/{$card->id}/spend-entries", ['year' => 2026, 'month' => 6, 'amount_spent' => 10000], $this->authHeaders($user));
        $this->postJson("/api/cards/{$card->id}/spend-entries", ['year' => 2026, 'month' => 6, 'amount_spent' => 30000], $this->authHeaders($user));

        $this->assertDatabaseCount('monthly_spend_entries', 1);
        $this->assertDatabaseHas('monthly_spend_entries', ['card_id' => $card->id, 'amount_spent' => 30000]);
    }

    public function test_user_cannot_log_spend_for_another_users_card(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $card = Card::factory()->for($other)->create();

        $this->postJson("/api/cards/{$card->id}/spend-entries", [
            'year' => 2026, 'month' => 6, 'amount_spent' => 1000,
        ], $this->authHeaders($user))->assertStatus(403);
    }

    public function test_monthly_report_aggregates_spend_and_best_card(): void
    {
        $user = User::factory()->create();
        $cardA = Card::factory()->for($user)->create(['card_name' => 'CardA']);
        $cardB = Card::factory()->for($user)->create(['card_name' => 'CardB']);

        $cardA->spendEntries()->create(['year' => 2026, 'month' => 6, 'amount_spent' => 15000]);
        $cardB->spendEntries()->create(['year' => 2026, 'month' => 6, 'amount_spent' => 40000]);

        $response = $this->getJson('/api/reports/monthly?year=2026&month=6', $this->authHeaders($user))
            ->assertStatus(200);

        $data = $response->json();
        $this->assertEqualsWithDelta(55000.0, $data['total_spend'], 0.01);
        $this->assertSame('CardB', $data['best_card_used']['card_name']);
    }

    public function test_monthly_report_only_counts_entries_for_requested_month(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create();
        $card->spendEntries()->create(['year' => 2026, 'month' => 5, 'amount_spent' => 99999]);
        $card->spendEntries()->create(['year' => 2026, 'month' => 6, 'amount_spent' => 1000]);

        $response = $this->getJson('/api/reports/monthly?year=2026&month=6', $this->authHeaders($user))
            ->assertStatus(200);

        $this->assertEqualsWithDelta(1000.0, $response->json('total_spend'), 0.01);
    }

    public function test_monthly_report_includes_potential_missed_rewards_for_expiring_unused_benefits(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create();
        Benefit::factory()->for($card)->create([
            'total_allowed' => 4,
            'used_count' => 1,
            'expiry_date' => '2026-06-20',
            'estimated_value' => 500,
        ]);
        // fully used benefit should not count
        Benefit::factory()->for($card)->create([
            'total_allowed' => 2,
            'used_count' => 2,
            'expiry_date' => '2026-06-20',
            'estimated_value' => 999,
        ]);

        $response = $this->getJson('/api/reports/monthly?year=2026&month=6', $this->authHeaders($user))
            ->assertStatus(200);

        $this->assertEqualsWithDelta(500.0, $response->json('potential_missed_rewards_value'), 0.01);
        $this->assertCount(1, $response->json('unused_benefits'));
    }

    public function test_monthly_report_requires_valid_year_and_month(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/reports/monthly?year=2026&month=13', $this->authHeaders($user))
            ->assertStatus(422);
    }
}
