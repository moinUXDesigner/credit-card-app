<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Card;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = auth('api')->login($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_dashboard_returns_overall_utilization(): void
    {
        $user = User::factory()->create();
        Card::factory()->for($user)->create(['total_limit' => 100000, 'current_outstanding' => 20000]);
        Card::factory()->for($user)->create(['total_limit' => 100000, 'current_outstanding' => 40000]);

        $response = $this->getJson('/api/dashboard', $this->authHeaders($user))->assertStatus(200);

        $this->assertEqualsWithDelta(30.0, $response->json('overall_utilization.percentage'), 0.01);
        $this->assertSame('caution', $response->json('overall_utilization.band'));
    }

    public function test_dashboard_lists_waiver_alerts_for_cards_with_remaining_spend(): void
    {
        $user = User::factory()->create();
        Card::factory()->for($user)->create([
            'card_name' => 'NeedsSpend',
            'waiver_spend_required' => 100000,
            'waiver_spend_completed' => 50000,
        ]);
        Card::factory()->for($user)->create([
            'card_name' => 'WaiverMet',
            'waiver_spend_required' => 100000,
            'waiver_spend_completed' => 100000,
        ]);

        $response = $this->getJson('/api/dashboard', $this->authHeaders($user))->assertStatus(200);

        $alerts = $response->json('waiver_alerts');
        $names = array_column($alerts, 'card_name');
        $this->assertContains('NeedsSpend', $names);
        $this->assertNotContains('WaiverMet', $names);
    }

    public function test_dashboard_lists_unused_benefits(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create();
        Benefit::factory()->for($card)->create(['total_allowed' => 4, 'used_count' => 1, 'title' => 'Lounge access']);
        Benefit::factory()->for($card)->create(['total_allowed' => 2, 'used_count' => 2, 'title' => 'Exhausted offer']);

        $response = $this->getJson('/api/dashboard', $this->authHeaders($user))->assertStatus(200);

        $titles = array_column($response->json('unused_benefits'), 'title');
        $this->assertContains('Lounge access', $titles);
        $this->assertNotContains('Exhausted offer', $titles);
    }

    public function test_dashboard_only_includes_own_active_cards(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Card::factory()->for($other)->create(['total_limit' => 100000, 'current_outstanding' => 100000]);
        Card::factory()->for($user)->create(['is_active' => false, 'total_limit' => 100000, 'current_outstanding' => 100000]);

        $response = $this->getJson('/api/dashboard', $this->authHeaders($user))->assertStatus(200);

        $this->assertEqualsWithDelta(0.0, $response->json('overall_utilization.percentage'), 0.01);
    }
}
