<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpendAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = auth('api')->login($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_spend_by_category_aggregates_across_cards_within_window_and_excludes_outside_entries(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 15));

        $user = User::factory()->create();
        $cardA = Card::factory()->for($user)->create();
        $cardB = Card::factory()->for($user)->create();

        // Within the trailing-6-month window (Feb 2026 - Jul 2026 inclusive).
        $cardA->spendEntries()->create(['year' => 2026, 'month' => 2, 'amount_spent' => 1000, 'category' => 'fuel']);
        $cardA->spendEntries()->create(['year' => 2026, 'month' => 3, 'amount_spent' => 500, 'category' => null]);
        $cardB->spendEntries()->create(['year' => 2026, 'month' => 7, 'amount_spent' => 2000, 'category' => 'dining']);

        // Outside the window (Jan 2026).
        $cardA->spendEntries()->create(['year' => 2026, 'month' => 1, 'amount_spent' => 5000, 'category' => 'fuel']);

        $response = $this->getJson('/api/reports/spend-by-category', $this->authHeaders($user))
            ->assertStatus(200);

        $data = $response->json();
        $this->assertSame(6, $data['months']);
        $this->assertEqualsWithDelta(3500.0, $data['total_spend'], 0.01);

        $categories = collect($data['categories'])->keyBy('category');
        $this->assertEqualsWithDelta(2000.0, $categories['dining']['amount'], 0.01);
        $this->assertEqualsWithDelta(1000.0, $categories['fuel']['amount'], 0.01);
        $this->assertEqualsWithDelta(500.0, $categories['uncategorized']['amount'], 0.01);
        $this->assertEqualsWithDelta(57.14, $categories['dining']['percentage'], 0.01);

        // Sorted descending by amount.
        $this->assertSame(['dining', 'fuel', 'uncategorized'], array_column($data['categories'], 'category'));

        Carbon::setTestNow();
    }

    public function test_spend_by_category_respects_months_param(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 15));

        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create();
        $card->spendEntries()->create(['year' => 2026, 'month' => 6, 'amount_spent' => 1000, 'category' => 'fuel']);
        $card->spendEntries()->create(['year' => 2026, 'month' => 7, 'amount_spent' => 2000, 'category' => 'dining']);

        $response = $this->getJson('/api/reports/spend-by-category?months=1', $this->authHeaders($user))
            ->assertStatus(200);

        $data = $response->json();
        $this->assertEqualsWithDelta(2000.0, $data['total_spend'], 0.01);
        $this->assertCount(1, $data['categories']);
        $this->assertSame('dining', $data['categories'][0]['category']);

        Carbon::setTestNow();
    }

    public function test_spend_by_category_rejects_invalid_months_value(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/reports/spend-by-category?months=5', $this->authHeaders($user))
            ->assertStatus(422);
    }

    public function test_spend_by_category_only_includes_own_cards(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 15));

        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherCard = Card::factory()->for($other)->create();
        $otherCard->spendEntries()->create(['year' => 2026, 'month' => 7, 'amount_spent' => 9999, 'category' => 'fuel']);

        $response = $this->getJson('/api/reports/spend-by-category', $this->authHeaders($user))
            ->assertStatus(200);

        $this->assertEqualsWithDelta(0.0, $response->json('total_spend'), 0.01);
        $this->assertSame([], $response->json('categories'));

        Carbon::setTestNow();
    }

    public function test_spend_by_category_with_no_entries_returns_empty_result(): void
    {
        $user = User::factory()->create();
        Card::factory()->for($user)->create();

        $response = $this->getJson('/api/reports/spend-by-category', $this->authHeaders($user))
            ->assertStatus(200);

        $this->assertEqualsWithDelta(0.0, $response->json('total_spend'), 0.01);
        $this->assertSame([], $response->json('categories'));
    }
}
