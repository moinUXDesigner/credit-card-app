<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlySpendEntryTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = auth('api')->login($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_storing_a_spend_entry_recalculates_waiver_progress(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 15));

        $user = User::factory()->create();
        $card = Card::factory()->create([
            'user_id' => $user->id,
            'card_year_start_month' => 9,
            'waiver_spend_required' => 300000,
            'waiver_spend_completed' => 0,
        ]);

        $this->postJson(
            "/api/cards/{$card->id}/spend-entries",
            ['year' => 2026, 'month' => 6, 'amount_spent' => 40000],
            $this->authHeaders($user)
        )->assertStatus(201);

        $this->assertDatabaseHas('cards', ['id' => $card->id, 'waiver_spend_completed' => 40000]);

        $this->postJson(
            "/api/cards/{$card->id}/spend-entries",
            ['year' => 2026, 'month' => 7, 'amount_spent' => 25000],
            $this->authHeaders($user)
        )->assertStatus(201);

        $this->assertDatabaseHas('cards', ['id' => $card->id, 'waiver_spend_completed' => 65000]);

        Carbon::setTestNow();
    }

    public function test_updating_an_existing_month_entry_replaces_rather_than_adds(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 15));

        $user = User::factory()->create();
        $card = Card::factory()->create([
            'user_id' => $user->id,
            'card_year_start_month' => 9,
            'waiver_spend_required' => 300000,
            'waiver_spend_completed' => 0,
        ]);

        $this->postJson(
            "/api/cards/{$card->id}/spend-entries",
            ['year' => 2026, 'month' => 6, 'amount_spent' => 40000],
            $this->authHeaders($user)
        )->assertStatus(201);

        $this->postJson(
            "/api/cards/{$card->id}/spend-entries",
            ['year' => 2026, 'month' => 6, 'amount_spent' => 10000],
            $this->authHeaders($user)
        )->assertStatus(201);

        $this->assertDatabaseHas('cards', ['id' => $card->id, 'waiver_spend_completed' => 10000]);

        Carbon::setTestNow();
    }

    public function test_spend_entries_outside_current_fee_year_cycle_are_excluded(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 15));

        $user = User::factory()->create();
        $card = Card::factory()->create([
            'user_id' => $user->id,
            'card_year_start_month' => 9,
            'waiver_spend_required' => 300000,
            'waiver_spend_completed' => 0,
        ]);

        // Cycle for "now" (Jun 2026) runs Sep 2025 - Aug 2026, so May 2025 falls outside it.
        $card->spendEntries()->create(['year' => 2025, 'month' => 5, 'amount_spent' => 99000]);

        $this->postJson(
            "/api/cards/{$card->id}/spend-entries",
            ['year' => 2026, 'month' => 6, 'amount_spent' => 40000],
            $this->authHeaders($user)
        )->assertStatus(201);

        $this->assertDatabaseHas('cards', ['id' => $card->id, 'waiver_spend_completed' => 40000]);

        Carbon::setTestNow();
    }

    public function test_user_cannot_add_spend_entry_to_another_users_card(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $card = Card::factory()->create(['user_id' => $owner->id]);

        $this->postJson(
            "/api/cards/{$card->id}/spend-entries",
            ['year' => 2026, 'month' => 6, 'amount_spent' => 1000],
            $this->authHeaders($other)
        )->assertStatus(403);
    }
}
