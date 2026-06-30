<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = auth('api')->login($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_recommendation_endpoint_returns_sorted_scores(): void
    {
        $user = User::factory()->create();
        $lowUtil = Card::factory()->for($user)->create(['total_limit' => 100000, 'current_outstanding' => 5000]);
        $highUtil = Card::factory()->for($user)->create(['total_limit' => 100000, 'current_outstanding' => 90000]);

        $response = $this->getJson('/api/recommendation', $this->authHeaders($user))->assertStatus(200);

        $data = $response->json();
        $this->assertCount(2, $data);
        $this->assertArrayHasKey('breakdown', $data[0]);
        $this->assertSame($lowUtil->id, $data[0]['card_id']);
        $this->assertSame($highUtil->id, $data[1]['card_id']);
    }

    public function test_recommendation_only_includes_own_active_cards(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Card::factory()->for($user)->create(['is_active' => false]);
        Card::factory()->for($other)->create();
        $own = Card::factory()->for($user)->create();

        $response = $this->getJson('/api/recommendation', $this->authHeaders($user))->assertStatus(200);

        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertSame($own->id, $data[0]['card_id']);
    }

    public function test_comparison_endpoint_returns_only_requested_owned_cards(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $cardA = Card::factory()->for($user)->create();
        $cardB = Card::factory()->for($user)->create();
        $othersCard = Card::factory()->for($other)->create();

        $response = $this->getJson(
            "/api/comparison?card_ids[]={$cardA->id}&card_ids[]={$othersCard->id}",
            $this->authHeaders($user)
        )->assertStatus(200);

        $ids = array_column($response->json(), 'id');
        $this->assertContains($cardA->id, $ids);
        $this->assertNotContains($othersCard->id, $ids);
        $this->assertNotContains($cardB->id, $ids);
    }
}
