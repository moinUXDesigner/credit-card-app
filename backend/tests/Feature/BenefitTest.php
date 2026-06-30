<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Card;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BenefitTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = auth('api')->login($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_user_can_create_benefit_for_own_card(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create();

        $payload = [
            'type' => 'lounge',
            'title' => 'Airport lounge access',
            'frequency' => 'quarterly',
            'total_allowed' => 4,
        ];

        $this->postJson("/api/cards/{$card->id}/benefits", $payload, $this->authHeaders($user))
            ->assertStatus(201)
            ->assertJsonPath('title', 'Airport lounge access')
            ->assertJsonPath('remaining', 4);

        $this->assertDatabaseHas('benefits', ['card_id' => $card->id, 'title' => 'Airport lounge access']);
    }

    public function test_user_cannot_create_benefit_for_another_users_card(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $card = Card::factory()->for($other)->create();

        $this->postJson("/api/cards/{$card->id}/benefits", [
            'type' => 'lounge',
            'title' => 'Hijacked benefit',
            'frequency' => 'quarterly',
            'total_allowed' => 4,
        ], $this->authHeaders($user))->assertStatus(403);
    }

    public function test_user_cannot_view_another_users_benefit(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $card = Card::factory()->for($other)->create();
        $benefit = Benefit::factory()->for($card)->create();

        $this->getJson("/api/benefits/{$benefit->id}", $this->authHeaders($user))->assertStatus(403);
    }

    public function test_user_cannot_update_another_users_benefit(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $card = Card::factory()->for($other)->create();
        $benefit = Benefit::factory()->for($card)->create();

        $this->putJson("/api/benefits/{$benefit->id}", ['title' => 'Hijacked'], $this->authHeaders($user))
            ->assertStatus(403);
    }

    public function test_user_cannot_delete_another_users_benefit(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $card = Card::factory()->for($other)->create();
        $benefit = Benefit::factory()->for($card)->create();

        $this->deleteJson("/api/benefits/{$benefit->id}", [], $this->authHeaders($user))->assertStatus(403);
        $this->assertDatabaseHas('benefits', ['id' => $benefit->id]);
    }

    public function test_mark_used_increments_count_and_logs_usage(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create();
        $benefit = Benefit::factory()->for($card)->create(['total_allowed' => 4, 'used_count' => 0]);

        $this->postJson("/api/benefits/{$benefit->id}/mark-used", [], $this->authHeaders($user))
            ->assertStatus(200)
            ->assertJsonPath('used_count', 1)
            ->assertJsonPath('remaining', 3);

        $this->assertDatabaseHas('benefit_usage_logs', ['benefit_id' => $benefit->id, 'quantity' => 1]);
    }

    public function test_user_cannot_mark_used_on_another_users_benefit(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $card = Card::factory()->for($other)->create();
        $benefit = Benefit::factory()->for($card)->create();

        $this->postJson("/api/benefits/{$benefit->id}/mark-used", [], $this->authHeaders($user))
            ->assertStatus(403);
    }

    public function test_owner_can_update_and_delete_own_benefit(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create();
        $benefit = Benefit::factory()->for($card)->create();

        $this->putJson("/api/benefits/{$benefit->id}", ['title' => 'Renamed'], $this->authHeaders($user))
            ->assertStatus(200)
            ->assertJsonPath('title', 'Renamed');

        $this->deleteJson("/api/benefits/{$benefit->id}", [], $this->authHeaders($user))->assertStatus(204);
        $this->assertDatabaseMissing('benefits', ['id' => $benefit->id]);
    }
}
