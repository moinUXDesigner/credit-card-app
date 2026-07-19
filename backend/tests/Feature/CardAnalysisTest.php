<?php

namespace Tests\Feature;

use App\Exceptions\CardAnalysisUnavailableException;
use App\Models\User;
use App\Services\CardAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CardAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = auth('api')->login($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_analyze_requires_authentication(): void
    {
        $file = UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf');

        $this->postJson('/api/cards/analyze', ['file' => $file])
            ->assertStatus(401);
    }

    public function test_analyze_requires_a_file(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/cards/analyze', [], $this->authHeaders($user))
            ->assertStatus(422);
    }

    public function test_analyze_rejects_unsupported_file_type(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

        $this->postJson('/api/cards/analyze', ['file' => $file], $this->authHeaders($user))
            ->assertStatus(422);
    }

    public function test_analyze_returns_prefill_data_on_success(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf');

        $fixture = [
            'analyzed' => true,
            'document_recognized' => true,
            'confidence' => 'high',
            'message' => null,
            'card' => [
                'card_name' => 'SBI Octane', 'bank_name' => 'SBI Card', 'last_four_digits' => '7245',
                'network' => 'visa', 'total_limit' => 250000.0, 'statement_day' => 12, 'due_day' => 2,
                'annual_fee_amount' => 1499.0, 'annual_fee_month' => 4, 'reward_point_balance' => null,
                'reward_rate_general' => 1.0, 'cashback_cap_amount' => 1000.0, 'lounge_access' => true,
                'best_categories' => ['fuel'],
            ],
            'suggested_benefits' => [
                ['type' => 'lounge', 'title' => 'Complimentary lounge access', 'frequency' => 'yearly', 'total_allowed' => 4, 'expiry_date' => null, 'estimated_value' => 2000.0],
            ],
        ];

        $this->mock(CardAnalysisService::class, function ($mock) use ($fixture) {
            $mock->shouldReceive('analyze')->once()->andReturn($fixture);
        });

        $response = $this->postJson('/api/cards/analyze', ['file' => $file], $this->authHeaders($user))
            ->assertStatus(200);

        $this->assertSame('SBI Octane', $response->json('card.card_name'));
        $this->assertCount(1, $response->json('suggested_benefits'));
    }

    public function test_analyze_returns_503_when_service_unavailable(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf');

        $this->mock(CardAnalysisService::class, function ($mock) {
            $mock->shouldReceive('analyze')->once()->andThrow(
                new CardAnalysisUnavailableException('The AI analysis service is temporarily unavailable. Please fill in the details manually.')
            );
        });

        $this->postJson('/api/cards/analyze', ['file' => $file], $this->authHeaders($user))
            ->assertStatus(503)
            ->assertJsonPath('message', 'The AI analysis service is temporarily unavailable. Please fill in the details manually.');
    }
}
