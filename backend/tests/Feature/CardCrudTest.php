<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardCrudTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = auth('api')->login($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_user_can_create_card(): void
    {
        $user = User::factory()->create();

        $payload = [
            'card_name' => 'Regalia',
            'bank_name' => 'HDFC',
            'last_four_digits' => '1234',
            'network' => 'visa',
            'total_limit' => 200000,
            'current_outstanding' => 35000,
            'statement_day' => 5,
            'due_day' => 25,
            'annual_fee_amount' => 2500,
            'annual_fee_month' => 9,
            'waiver_spend_required' => 300000,
            'waiver_spend_completed' => 210000,
            'card_year_start_month' => 9,
        ];

        $this->postJson('/api/cards', $payload, $this->authHeaders($user))
            ->assertStatus(201)
            ->assertJsonPath('card_name', 'Regalia')
            ->assertJsonPath('bank_name', 'HDFC');

        $this->assertDatabaseHas('cards', ['card_name' => 'Regalia', 'user_id' => $user->id]);
    }

    public function test_create_card_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/cards', [], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['card_name', 'bank_name', 'last_four_digits', 'network']);
    }

    public function test_user_sees_only_own_cards_in_index(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Card::factory()->for($user)->create(['card_name' => 'Mine']);
        Card::factory()->for($other)->create(['card_name' => 'Theirs']);

        $response = $this->getJson('/api/cards', $this->authHeaders($user))->assertStatus(200);
        $names = array_column($response->json(), 'card_name');

        $this->assertContains('Mine', $names);
        $this->assertNotContains('Theirs', $names);
    }

    public function test_user_cannot_view_another_users_card(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $card = Card::factory()->for($other)->create();

        $this->getJson("/api/cards/{$card->id}", $this->authHeaders($user))
            ->assertStatus(403);
    }

    public function test_user_cannot_update_another_users_card(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $card = Card::factory()->for($other)->create();

        $this->putJson("/api/cards/{$card->id}", ['card_name' => 'Hijacked'], $this->authHeaders($user))
            ->assertStatus(403);
    }

    public function test_user_cannot_delete_another_users_card(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $card = Card::factory()->for($other)->create();

        $this->deleteJson("/api/cards/{$card->id}", [], $this->authHeaders($user))
            ->assertStatus(403);

        $this->assertDatabaseHas('cards', ['id' => $card->id]);
    }

    public function test_owner_can_update_and_delete_own_card(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create();

        $this->putJson("/api/cards/{$card->id}", ['card_name' => 'Renamed'], $this->authHeaders($user))
            ->assertStatus(200)
            ->assertJsonPath('card_name', 'Renamed');

        $this->deleteJson("/api/cards/{$card->id}", [], $this->authHeaders($user))
            ->assertStatus(204);

        $this->assertDatabaseMissing('cards', ['id' => $card->id]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/cards')->assertStatus(401);
    }
}
