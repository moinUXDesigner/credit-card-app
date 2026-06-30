<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_validates_input(): void
    {
        $this->postJson('/api/auth/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@test.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'New User',
            'email' => 'taken@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_register_returns_token(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'New User',
            'email' => 'new@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(200)->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'user']);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'demo@test.com',
            'password' => Hash::make('correct-password'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'demo@test.com',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_login_returns_token_for_correct_credentials(): void
    {
        User::factory()->create([
            'email' => 'demo@test.com',
            'password' => Hash::make('correct-password'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'demo@test.com',
            'password' => 'correct-password',
        ])->assertStatus(200)->assertJsonStructure(['access_token']);
    }

    public function test_protected_route_rejects_missing_token(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_protected_route_rejects_invalid_token(): void
    {
        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer invalid-token'])
            ->assertStatus(401);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create(['email' => 'demo@test.com']);
        $token = auth('api')->login($user);

        $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJson(['id' => $user->id, 'email' => 'demo@test.com']);
    }
}
