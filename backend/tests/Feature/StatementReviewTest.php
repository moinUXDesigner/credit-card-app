<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\StatementPreview;
use App\Models\User;
use App\Services\StatementAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StatementReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer '.auth('api')->login($user)];
    }

    private function preview(User $user, ?Card $card, array $summary = [], array $rows = [], bool $analyzed = true): array
    {
        $result = ['analyzed' => $analyzed, 'message' => $analyzed ? null : 'Unavailable', 'statement' => array_replace(['last_four_digits' => $card?->last_four_digits, 'statement_date' => '2026-07-12', 'due_date' => '2026-08-01', 'total_due' => 15000, 'minimum_due' => 500, 'credit_limit' => 100000, 'reward_point_balance' => 850], $summary), 'transactions' => $rows];
        $this->mock(StatementAnalysisService::class, fn ($mock) => $mock->shouldReceive('analyze')->once()->andReturn($result));

        return $this->postJson('/api/statement-previews', ['file' => UploadedFile::fake()->createWithContent('statement.pdf', '%PDF-1.4 '.Str::uuid()), 'card_id' => $card?->id], $this->headers($user))->assertCreated()->json();
    }

    private function payload(Card $card, array $preview): array
    {
        $summary = $preview['summary'];
        unset($summary['last_four_digits']);
        $rows = array_map(function ($row) {
            unset($row['possible_duplicate']);

            return $row;
        }, $preview['rows']);

        return ['preview_id' => $preview['preview_id'], 'idempotency_key' => (string) Str::uuid(), 'revision' => $card->fresh()->revision,
            'billing_month' => 7, 'billing_year' => 2026, 'summary' => $summary, 'rows' => $rows,
            'apply_summary' => $preview['apply_defaults'], 'acknowledge_identity' => false, 'save_pdf_only' => false];
    }

    private function confirm(User $user, Card $card, array $payload)
    {
        return $this->postJson("/api/cards/{$card->id}/statements", $payload, $this->headers($user));
    }

    public function test_preview_changes_nothing_and_confirmation_preserves_manual_spend_and_credits(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['current_outstanding' => 100, 'card_year_start_month' => 1]);
        $card->spendEntries()->create(['year' => 2026, 'month' => 7, 'category' => 'fuel', 'manual_amount' => 200, 'amount_spent' => 200]);
        $preview = $this->preview($user, $card, [], [['date' => '2026-07-01', 'description' => 'Fuel', 'amount' => 1000, 'category' => 'fuel'], ['date' => '2026-07-02', 'description' => 'Refund', 'amount' => 100, 'category' => 'fuel', 'direction' => 'credit']]);
        $this->assertDatabaseCount('statements', 0);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertEquals(100, $card->fresh()->current_outstanding);
        $this->confirm($user, $card, $this->payload($card, $preview))->assertCreated();
        $this->assertEquals(15000, $card->fresh()->current_outstanding);
        $this->assertDatabaseHas('monthly_spend_entries', ['card_id' => $card->id, 'manual_amount' => 200, 'amount_spent' => 1200]);
    }

    public function test_receipt_replays_old_revision_but_rejects_changed_content_and_deleted_statement(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create();
        $preview = $this->preview($user, $card);
        $payload = $this->payload($card, $preview);
        $id = $this->confirm($user, $card, $payload)->assertCreated()->json('id');
        $this->confirm($user, $card, $payload)->assertOk()->assertJsonPath('id', $id);
        $this->confirm($user, $card, array_replace($payload, ['billing_month' => 8]))->assertStatus(422);
        $this->deleteJson("/api/statements/{$id}", [], $this->headers($user))->assertNoContent();
        $this->confirm($user, $card, $payload)->assertStatus(410);
    }

    public function test_historical_import_does_not_replace_current_balance_and_deletion_restores_older_summary(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['current_outstanding' => 100]);
        $newer = $this->preview($user, $card, ['statement_date' => '2026-08-12', 'total_due' => 20000]);
        $id = $this->confirm($user, $card, $this->payload($card, $newer))->assertCreated()->json('id');
        $older = $this->preview($user, $card);
        $payload = $this->payload($card, $older);
        $this->assertFalse($payload['apply_summary']['current_outstanding']);
        $this->confirm($user, $card, $payload)->assertCreated();
        $this->assertEquals(20000, $card->fresh()->current_outstanding);
        $this->deleteJson("/api/statements/{$id}", [], $this->headers($user))->assertNoContent();
        $this->assertEquals(15000, $card->fresh()->current_outstanding);
    }

    public function test_manual_balance_is_preserved_on_deletion_and_old_imports(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create();
        $preview = $this->preview($user, $card);
        $id = $this->confirm($user, $card, $this->payload($card, $preview))->assertCreated()->json('id');
        $this->putJson("/api/cards/{$card->id}", ['current_outstanding' => 1234], $this->headers($user))->assertOk();
        $older = $this->preview($user, $card);
        $payload = $this->payload($card, $older);
        $payload['apply_summary']['current_outstanding'] = true;
        $this->confirm($user, $card, $payload)->assertStatus(422);
        $this->deleteJson("/api/statements/{$id}", [], $this->headers($user))->assertNoContent();
        $this->assertEquals(1234, $card->fresh()->current_outstanding);
    }

    public function test_duplicate_rows_require_review_and_skip_keeps_manual_spend(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create();
        $card->transactions()->create(['transaction_date' => '2026-07-01', 'description' => '  FUEL   station ', 'amount' => 1000, 'category' => 'fuel', 'direction' => 'purchase']);
        $preview = $this->preview($user, $card, [], [['date' => '2026-07-01', 'description' => 'Fuel station', 'amount' => 1000, 'category' => 'fuel']]);
        $this->assertTrue($preview['rows'][0]['possible_duplicate']);
        $payload = $this->payload($card, $preview);
        $this->confirm($user, $card, $payload)->assertStatus(422);
        $this->assertDatabaseCount('statements', 0);
        $payload['rows'][0]['duplicate_action'] = 'skip';
        $this->confirm($user, $card, $payload)->assertCreated();
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_mismatched_identity_blocks_and_missing_identity_requires_acknowledgement(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['last_four_digits' => '0001']);
        $preview = $this->preview($user, $card, ['last_four_digits' => '9999']);
        $this->confirm($user, $card, $this->payload($card, $preview))->assertStatus(422);
        $preview = $this->preview($user, $card, ['last_four_digits' => null]);
        $payload = $this->payload($card, $preview);
        $this->confirm($user, $card, $payload)->assertStatus(422);
        $payload['acknowledge_identity'] = true;
        $this->confirm($user, $card, $payload)->assertCreated();
    }

    public function test_failed_extraction_can_save_pdf_only_without_any_updates(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['current_outstanding' => 100]);
        $preview = $this->preview($user, $card, [], [], false);
        $payload = $this->payload($card, $preview);
        $this->confirm($user, $card, $payload)->assertStatus(422);
        $payload['save_pdf_only'] = true;
        $this->confirm($user, $card, $payload)->assertCreated()->assertJsonPath('analysis_status', 'failed');
        $this->assertEquals(100, $card->fresh()->current_outstanding);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_preview_ownership_expiry_revision_and_shared_limit_validation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $card = Card::factory()->for($user)->create(['total_limit' => 100000, 'shared_limit_group' => 'pool']);
        Card::factory()->for($user)->create(['total_limit' => 100000, 'shared_limit_group' => 'pool']);
        $preview = $this->preview($user, $card, ['credit_limit' => 200000]);
        $payload = $this->payload($card, $preview);
        $this->getJson('/api/statement-previews/'.$preview['preview_id'], $this->headers($other))->assertForbidden();
        $this->confirm($other, $card, $payload)->assertForbidden();
        $payload['apply_summary']['total_limit'] = true;
        $this->confirm($user, $card, $payload)->assertStatus(422);
        $payload['apply_summary']['total_limit'] = false;
        $payload['revision'] = 999;
        $this->confirm($user, $card, $payload)->assertStatus(409);
        $payload['revision'] = $card->fresh()->revision;
        StatementPreview::find($preview['preview_id'])->update(['expires_at' => now()->subMinute()]);
        $this->confirm($user, $card, $payload)->assertStatus(422);
        $this->artisan('statements:cleanup-previews')->assertSuccessful();
        $this->assertDatabaseCount('statement_previews', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_new_card_can_attach_unbound_preview(): void
    {
        $user = User::factory()->create();
        $preview = $this->preview($user, null, ['last_four_digits' => '0001']);
        $card = Card::factory()->for($user)->create(['last_four_digits' => '0001']);
        $this->confirm($user, $card, $this->payload($card, $preview))->assertCreated()->assertJsonPath('card_id', $card->id);
        $this->assertEquals($card->id, StatementPreview::find($preview['preview_id'])->card_id);
    }

    public function test_offline_upload_stages_review_and_confirm_replay_is_idempotent(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['current_outstanding' => 100]);
        $this->mock(StatementAnalysisService::class, fn ($mock) => $mock->shouldReceive('analyze')->once()->andReturn(['analyzed' => true, 'message' => null, 'statement' => ['total_due' => 15000], 'transactions' => []]));
        $operation = ['operation_id' => (string) Str::uuid(), 'method' => 'POST', 'path' => "/cards/{$card->id}/statements", 'revision' => $card->revision, 'payload' => ['billing_month' => 7, 'billing_year' => 2026], 'file' => UploadedFile::fake()->create('statement.pdf', 1, 'application/pdf')];
        $staged = $this->postJson('/api/sync/mutations', $operation, $this->headers($user))->assertOk()->assertJsonPath('data.analysis_status', 'pending')->json('data');
        $this->assertEquals(100, $card->fresh()->current_outstanding);
        $preview = $this->getJson('/api/statement-previews/'.$staged['preview_id'], $this->headers($user))->assertOk()->json();
        $payload = $this->payload($card, $preview);
        $payload['acknowledge_identity'] = true;
        $operation = ['operation_id' => (string) Str::uuid(), 'method' => 'POST', 'path' => "/cards/{$card->id}/statements", 'revision' => $card->fresh()->revision, 'payload' => $payload];
        $this->postJson('/api/sync/mutations', $operation, $this->headers($user))->assertOk()->assertJsonPath('data.analysis_status', 'completed');
        $this->postJson('/api/sync/mutations', $operation, $this->headers($user))->assertOk();
        $this->assertEquals(15000, $card->fresh()->current_outstanding);
        $this->assertDatabaseCount('statements', 1);
    }

    public function test_pdf_only_ui_payload_allows_empty_summary_selection_and_can_be_reanalyzed(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['current_outstanding' => 100]);
        $preview = $this->preview($user, $card, [], [], false);
        $payload = $this->payload($card, $preview);
        $payload['apply_summary'] = [];
        $payload['save_pdf_only'] = true;
        $id = $this->confirm($user, $card, $payload)->assertCreated()->json('id');
        $this->mock(StatementAnalysisService::class, fn ($mock) => $mock->shouldReceive('analyze')->once()->andReturn(['analyzed' => true, 'message' => null, 'statement' => ['last_four_digits' => $card->last_four_digits, 'statement_date' => '2026-07-12', 'total_due' => 15000], 'transactions' => []]));
        $retry = $this->postJson('/api/statement-previews', ['statement_id' => $id], $this->headers($user))->assertCreated()->json();
        $this->confirm($user, $card, $this->payload($card, $retry))->assertCreated()->assertJsonPath('id', $id)->assertJsonPath('analysis_status', 'completed');
        $this->assertEquals(15000, $card->fresh()->current_outstanding);
        $this->assertDatabaseCount('statements', 1);
    }

    public function test_editor_can_review_and_import_but_viewer_cannot(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $viewer = User::factory()->create();
        $card = Card::factory()->for($owner)->create();
        foreach ([$editor->id => 'editor', $viewer->id => 'viewer'] as $userId => $permission) {
            DB::table('card_memberships')->insert(['card_id' => $card->id, 'user_id' => $userId, 'permission' => $permission, 'accepted_at' => now(), 'expires_at' => now()->addWeek(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->postJson('/api/statement-previews', ['card_id' => $card->id, 'file' => UploadedFile::fake()->create('statement.pdf', 1, 'application/pdf')], $this->headers($viewer))->assertForbidden();
        $preview = $this->preview($editor, $card);
        $this->confirm($editor, $card, $this->payload($card, $preview))->assertCreated();
        $this->getJson("/api/cards/{$card->id}/statements", $this->headers($viewer))->assertOk()->assertJsonCount(1);
    }

    public function test_expired_offline_confirmation_retains_pdf_and_requires_fresh_review(): void
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['current_outstanding' => 100]);
        $preview = $this->preview($user, $card);
        $payload = $this->payload($card, $preview);
        $draft = StatementPreview::find($preview['preview_id']);
        $file = new UploadedFile(Storage::disk('local')->path($draft->file_path), 'statement.pdf', 'application/pdf', null, true);
        $draft->update(['expires_at' => now()->subMinute()]);
        $this->mock(StatementAnalysisService::class, fn ($mock) => $mock->shouldReceive('analyze')->once()->andReturn(['analyzed' => false, 'message' => 'Unavailable', 'statement' => [], 'transactions' => []]));
        $operation = ['operation_id' => (string) Str::uuid(), 'method' => 'POST', 'path' => "/cards/{$card->id}/statements", 'revision' => $card->fresh()->revision, 'payload' => $payload, 'file' => $file];
        $result = $this->postJson('/api/sync/mutations', $operation, $this->headers($user))->assertOk()->assertJsonPath('data.requires_review', true)->json('data');
        $this->assertEquals(100, $card->fresh()->current_outstanding);
        $this->assertDatabaseCount('transactions', 0);
        $this->getJson('/api/statements/'.$result['id'].'/download', $this->headers($user))->assertOk();
        $this->postJson('/api/sync/mutations', $operation, $this->headers($user))->assertOk();
        $this->assertDatabaseCount('statements', 1);
    }
}
