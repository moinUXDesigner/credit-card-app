<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use App\Services\StatementAnalysisService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StatementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Carbon::setTestNow('2026-07-15');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function authHeaders(User $user): array
    {
        $token = auth('api')->login($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    private function fixtureAnalysis(): array
    {
        return [
            'analyzed' => true,
            'message' => null,
            'statement' => [
                'statement_date' => '2026-07-12',
                'due_date' => '2026-07-30',
                'total_due' => 15000.0,
                'minimum_due' => 1500.0,
                'credit_limit' => 200000.0,
                'reward_point_balance' => 850.0,
            ],
            'transactions' => [
                ['date' => '2026-06-18', 'description' => 'INDIAN OIL PETROL PUMP', 'amount' => 3000.0, 'category' => 'fuel'],
                ['date' => '2026-06-20', 'description' => 'BIG BAZAAR', 'amount' => 5000.0, 'category' => 'grocery'],
                ['date' => '2026-06-22', 'description' => 'UNKNOWN MERCHANT XYZ', 'amount' => 7000.0, 'category' => 'fuel'],
            ],
        ];
    }

    private function mockAnalysisService(array $result): void
    {
        $this->mock(StatementAnalysisService::class, function ($mock) use ($result) {
            $mock->shouldReceive('analyze')->once()->andReturn($result);
        });
    }

    private function statementFile(): UploadedFile
    {
        return UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf');
    }

    private function confirmStaged(User $user, Card $card, string $previewId, array $overrides = [])
    {
        $preview = $this->getJson("/api/statement-previews/{$previewId}", $this->authHeaders($user))->assertOk()->json();
        $summary = array_intersect_key($preview['summary'], array_flip(['statement_date', 'due_date', 'total_due', 'minimum_due', 'credit_limit', 'reward_point_balance']));
        $rows = array_map(function ($row) {
            unset($row['possible_duplicate']);
            $row['duplicate_action'] = 'keep';

            return $row;
        }, $preview['rows']);

        return $this->postJson("/api/cards/{$card->id}/statements", array_replace([
            'preview_id' => $previewId, 'idempotency_key' => (string) Str::uuid(),
            'revision' => $card->fresh()->revision, 'billing_month' => 7, 'billing_year' => 2026,
            'summary' => $summary, 'rows' => $rows, 'acknowledge_identity' => true, 'save_pdf_only' => false,
            'apply_summary' => $preview['apply_defaults'],
        ], $overrides), $this->authHeaders($user));
    }

    public function test_user_can_upload_statement_and_it_updates_card_and_spend(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['current_outstanding' => 0, 'waiver_spend_completed' => 0, 'card_year_start_month' => 1]);
        $this->mockAnalysisService($this->fixtureAnalysis());

        $response = $this->postJson(
            "/api/cards/{$card->id}/statements",
            ['file' => $this->statementFile(), 'billing_month' => 7, 'billing_year' => 2026],
            $this->authHeaders($user)
        );

        $response->assertStatus(201)->assertJsonPath('analysis_status', 'pending')->assertJsonCount(0, 'transactions');
        $this->assertEquals(0, $card->fresh()->current_outstanding);
        $this->assertDatabaseCount('transactions', 0);
        $response = $this->confirmStaged($user, $card, $response->json('preview_id'));
        $response->assertStatus(201)
            ->assertJsonPath('analysis_status', 'completed')
            ->assertJsonPath('total_due', 15000)
            ->assertJsonCount(3, 'transactions');

        $this->assertDatabaseHas('transactions', ['card_id' => $card->id, 'description' => 'BIG BAZAAR', 'category' => 'grocery']);
        $this->assertDatabaseHas('monthly_spend_entries', [
            'card_id' => $card->id, 'year' => 2026, 'month' => 6, 'category' => 'fuel', 'amount_spent' => 10000.00,
        ]);
        $this->assertDatabaseHas('monthly_spend_entries', [
            'card_id' => $card->id, 'year' => 2026, 'month' => 6, 'category' => 'grocery', 'amount_spent' => 5000.00,
        ]);

        $card->refresh();
        $this->assertEqualsWithDelta(15000.0, $card->current_outstanding, 0.01);
        $this->assertEqualsWithDelta(850.0, $card->reward_point_balance, 0.01);
        $this->assertEqualsWithDelta(15000.0, $card->waiver_spend_completed, 0.01);
    }

    public function test_reuploading_a_statement_never_double_counts(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['card_year_start_month' => 1]);
        $this->mockAnalysisService($this->fixtureAnalysis());
        $staged = $this->postJson("/api/cards/{$card->id}/statements", ['file' => $this->statementFile(), 'billing_month' => 7, 'billing_year' => 2026], $this->authHeaders($user))->assertCreated();
        $confirmed = $this->confirmStaged($user, $card, $staged->json('preview_id'))->assertCreated();
        $this->postJson("/api/cards/{$card->id}/statements", ['file' => $this->statementFile(), 'billing_month' => 7, 'billing_year' => 2026], $this->authHeaders($user))->assertOk()->assertJsonPath('id', $confirmed->json('id'));
        $this->confirmStaged($user, $card, $staged->json('preview_id'))->assertOk();
        $this->assertDatabaseCount('statements', 1);
        $this->assertDatabaseCount('transactions', 3);
        $this->assertDatabaseHas('monthly_spend_entries', ['card_id' => $card->id, 'year' => 2026, 'month' => 6, 'category' => 'fuel', 'amount_spent' => 10000.00]);
    }

    public function test_upload_rejects_non_pdf_and_missing_billing_period(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['card_year_start_month' => 1]);

        $this->postJson(
            "/api/cards/{$card->id}/statements",
            ['file' => UploadedFile::fake()->create('statement.txt', 10, 'text/plain'), 'billing_month' => 7, 'billing_year' => 2026],
            $this->authHeaders($user)
        )->assertStatus(422)->assertJsonValidationErrors(['file']);

        $this->postJson(
            "/api/cards/{$card->id}/statements",
            ['file' => $this->statementFile()],
            $this->authHeaders($user)
        )->assertStatus(422)->assertJsonValidationErrors(['billing_month', 'billing_year']);
    }

    public function test_user_cannot_upload_statement_to_another_users_card(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $card = Card::factory()->for($other)->create();

        $this->postJson(
            "/api/cards/{$card->id}/statements",
            ['file' => $this->statementFile(), 'billing_month' => 7, 'billing_year' => 2026],
            $this->authHeaders($user)
        )->assertStatus(403);
    }

    public function test_user_can_list_own_statements(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['card_year_start_month' => 1]);
        $this->mockAnalysisService($this->fixtureAnalysis());

        $this->postJson(
            "/api/cards/{$card->id}/statements",
            ['file' => $this->statementFile(), 'billing_month' => 7, 'billing_year' => 2026],
            $this->authHeaders($user)
        );

        $this->getJson("/api/cards/{$card->id}/statements", $this->authHeaders($user))
            ->assertStatus(200)
            ->assertJsonCount(1);
    }

    public function test_user_cannot_view_or_delete_another_users_statement(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $card = Card::factory()->for($other)->create();
        $this->mockAnalysisService($this->fixtureAnalysis());

        $statementId = $this->postJson(
            "/api/cards/{$card->id}/statements",
            ['file' => $this->statementFile(), 'billing_month' => 7, 'billing_year' => 2026],
            $this->authHeaders($other)
        )->json('id');

        $this->getJson("/api/statements/{$statementId}", $this->authHeaders($user))->assertStatus(403);
        $this->getJson("/api/statements/{$statementId}/download", $this->authHeaders($user))->assertStatus(403);
        $this->deleteJson("/api/statements/{$statementId}", [], $this->authHeaders($user))->assertStatus(403);
    }

    public function test_owner_can_download_and_delete_statement(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['card_year_start_month' => 1]);
        $this->mockAnalysisService($this->fixtureAnalysis());

        $staged = $this->postJson(
            "/api/cards/{$card->id}/statements",
            ['file' => $this->statementFile(), 'billing_month' => 7, 'billing_year' => 2026],
            $this->authHeaders($user)
        );
        $statementId = $this->confirmStaged($user, $card, $staged->json('preview_id'))->assertCreated()->json('id');

        $this->getJson("/api/statements/{$statementId}/download", $this->authHeaders($user))->assertStatus(200);

        $this->deleteJson("/api/statements/{$statementId}", [], $this->authHeaders($user))->assertStatus(204);

        $this->assertDatabaseMissing('statements', ['id' => $statementId]);
        $this->assertDatabaseMissing('transactions', ['statement_id' => $statementId]);
        $this->assertDatabaseHas('monthly_spend_entries', [
            'card_id' => $card->id, 'year' => 2026, 'month' => 6, 'category' => 'fuel', 'amount_spent' => 0,
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $card = Card::factory()->for(User::factory()->create())->create();

        $this->getJson("/api/cards/{$card->id}/statements")->assertStatus(401);
    }
}
