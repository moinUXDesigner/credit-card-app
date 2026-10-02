<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Card;
use App\Models\User;
use App\Services\StatementAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class CollaborationTest extends TestCase
{
    use RefreshDatabase;

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        app('auth')->forgetGuards();
        if (isset($server['HTTP_AUTHORIZATION'])) {
            app('tymon.jwt')->setToken(substr($server['HTTP_AUTHORIZATION'], 7));
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    private function headers(User $u): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($u)];
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = 'admin';
        $u->save();

        return $u;
    }

    private function member(Card $c, User $u, string $permission = 'viewer'): void
    {
        DB::table('card_memberships')->insert(['card_id' => $c->id, 'user_id' => $u->id, 'permission' => $permission, 'accepted_at' => now(), 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_admin_has_account_access_but_not_personal_access(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        $card = Card::factory()->for($owner)->create();
        $h = $this->headers($admin);
        $this->getJson('/api/admin/users', $h)->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/admin/health', $h)->assertOk()->assertJsonMissing(['password']);
        $this->getJson("/api/cards/$card->id", $h)->assertForbidden();
        $this->getJson('/api/admin/users', $this->headers($owner))->assertForbidden();
    }

    public function test_suspension_is_audited_and_denies_existing_tokens_and_refresh(): void
    {
        $a = $this->admin();
        $u = User::factory()->create();
        $h = $this->headers($u);
        $this->patchJson("/api/admin/users/$u->id/status", ['suspended' => true], $this->headers($a))->assertOk();
        $this->assertDatabaseHas('audit_events', ['target_id' => $u->id, 'action' => 'suspend']);
        $this->getJson('/api/cards', $h)->assertForbidden();
        $this->postJson('/api/auth/refresh', [], $h)->assertForbidden();
        $this->patchJson("/api/admin/users/$a->id/status", ['suspended' => true], $this->headers($a))->assertUnprocessable();
        $this->patchJson("/api/admin/users/$u->id/status", ['suspended' => false], $this->headers($a))->assertOk();
        $this->getJson('/api/cards', $h)->assertOk();
    }

    public function test_registration_cannot_assign_admin(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Example', 'email' => 'new@example.com', 'password' => 'Password@123', 'password_confirmation' => 'Password@123', 'role' => 'admin'])->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'role' => 'user']);
    }

    public function test_role_command_protects_last_active_admin(): void
    {
        $a = $this->admin();
        $this->expectException(\RuntimeException::class);
        $this->artisan('users:role', ['email' => $a->email, 'role' => 'user']);
    }

    public function test_invitation_acceptance_and_nested_permission_matrix(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $card = Card::factory()->for($owner)->create();
        $benefit = Benefit::factory()->for($card)->create();
        $oh = $this->headers($owner);
        $vh = $this->headers($viewer);
        $this->postJson("/api/cards/$card->id/sharing", ['email' => $viewer->email, 'permission' => 'viewer'], $oh)->assertCreated();
        $this->getJson("/api/cards/$card->id", $vh)->assertForbidden();
        $id = DB::table('card_memberships')->value('id');
        $this->postJson("/api/invitations/$id/accept", [], $vh)->assertOk();
        $this->getJson('/api/cards', $vh)->assertJsonCount(1)->assertJsonPath('0.permission', 'viewer');
        $this->getJson("/api/benefits/$benefit->id", $vh)->assertOk();
        $this->putJson("/api/cards/$card->id", ['card_name' => 'No'], $vh)->assertForbidden();
        $this->postJson("/api/benefits/$benefit->id/mark-used", [], $vh)->assertForbidden();
        $this->patchJson("/api/cards/$card->id/sharing/$id", ['permission' => 'editor'], $oh)->assertOk();
        $this->putJson("/api/cards/$card->id", ['card_name' => 'Edited'], $vh)->assertOk();
        $this->postJson("/api/benefits/$benefit->id/mark-used", [], $vh)->assertOk();
        $this->deleteJson("/api/cards/$card->id", [], $vh)->assertForbidden();
        $this->deleteJson("/api/cards/$card->id/sharing/$id", [], $oh)->assertNoContent();
        $this->getJson('/api/sync/bootstrap', $vh)->assertJsonCount(0, 'cards');
    }

    public function test_expired_and_other_recipient_invitations_cannot_be_accepted(): void
    {
        $c = Card::factory()->create();
        $u = User::factory()->create();
        DB::table('card_memberships')->insert(['card_id' => $c->id, 'user_id' => $u->id, 'permission' => 'viewer', 'expires_at' => now()->subDay()]);
        $id = DB::table('card_memberships')->value('id');
        $this->postJson("/api/invitations/$id/accept", [], $this->headers($u))->assertNotFound();
        $this->postJson("/api/invitations/$id/accept", [], $this->headers(User::factory()->create()))->assertNotFound();
    }

    public function test_shared_card_is_available_in_aggregate_endpoints(): void
    {
        $c = Card::factory()->create();
        $u = User::factory()->create();
        $this->member($c, $u);
        $h = $this->headers($u);
        foreach (['/api/dashboard', '/api/comparison', '/api/recommendation', '/api/reports/monthly?year=2026&month=10', '/api/reports/spend-by-category?year=2026&month=10'] as $url) {
            $this->getJson($url, $h)->assertOk();
        }$this->getJson('/api/recommendation', $h)->assertJsonPath('0.card_id', $c->id);
    }

    public function test_message_preview_confirmation_duplicate_and_manual_preservation(): void
    {
        $c = Card::factory()->create(['card_year_start_month' => 1]);
        $h = $this->headers($c->user);
        $this->postJson("/api/cards/$c->id/spend-entries", ['year' => 2026, 'month' => 10, 'amount_spent' => 100, 'category' => 'other'], $h)->assertCreated();
        $message = 'INR 500 spent at Grocery Store on 2026-10-01';
        $preview = $this->postJson("/api/cards/$c->id/messages/preview", ['text' => $message], $h)->assertOk()->json();
        $this->assertDatabaseCount('transactions', 0);
        $rows = $preview['rows'];
        $this->postJson("/api/cards/$c->id/messages/confirm", ['preview_id' => $preview['preview_id'], 'rows' => $rows, 'apply_summary' => false], $h)->assertCreated()->assertJsonPath('imported', 1);
        $this->assertDatabaseHas('monthly_spend_entries', ['card_id' => $c->id, 'amount_spent' => 600, 'manual_amount' => 100]);
        $this->postJson("/api/cards/$c->id/messages/preview", ['text' => $message], $h)->assertJsonPath('duplicate', true);
        $p = $this->postJson("/api/cards/$c->id/messages/preview", ['text' => $message.' again'], $h)->json();
        $this->assertTrue($p['rows'][0]['possible_duplicate']);
        $this->postJson("/api/cards/$c->id/messages/confirm", ['preview_id' => $p['preview_id'], 'rows' => $p['rows'], 'apply_summary' => false], $h)->assertUnprocessable();
        $p['rows'][0]['duplicate_action'] = 'skip';
        $this->postJson("/api/cards/$c->id/messages/confirm", ['preview_id' => $p['preview_id'], 'rows' => $p['rows'], 'apply_summary' => false], $h)->assertCreated()->assertJsonPath('imported', 0);
    }

    public function test_credits_and_summary_require_explicit_confirmation(): void
    {
        $c = Card::factory()->create(['current_outstanding' => 100]);
        $h = $this->headers($c->user);
        $p = $this->postJson("/api/cards/$c->id/messages/preview", ['text' => "INR 300 credited on 2026-10-01\nTotal due: INR 250"], $h)->assertOk()->json();
        $this->postJson("/api/cards/$c->id/messages/confirm", ['preview_id' => $p['preview_id'], 'rows' => $p['rows'], 'apply_summary' => false], $h)->assertCreated();
        $this->assertEquals(100, $c->fresh()->current_outstanding);
        $this->assertEquals(0, $c->spendEntries()->sum('amount_spent'));
    }

    public function test_ambiguous_date_and_unsupported_input_are_not_silently_saved(): void
    {
        $c = Card::factory()->create();
        $h = $this->headers($c->user);
        $p = $this->postJson("/api/cards/$c->id/messages/preview", ['text' => 'INR 200 spent on 01/02/2026'], $h)->assertOk()->json();
        $this->assertNull($p['rows'][0]['transaction_date']);
        $this->postJson("/api/cards/$c->id/messages/confirm", ['preview_id' => $p['preview_id'], 'rows' => $p['rows'], 'apply_summary' => false], $h)->assertUnprocessable();
        $this->postJson("/api/cards/$c->id/messages/preview", ['text' => 'Hello world'], $h)->assertUnprocessable();
    }

    public function test_eml_base64_parsing_and_file_validation(): void
    {
        $c = Card::factory()->create();
        $h = $this->headers($c->user);
        $file = UploadedFile::fake()->createWithContent('message.eml', "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".base64_encode('INR 500 spent on 2026-10-01'));
        $this->post("/api/cards/$c->id/messages/preview", ['file' => $file], $h + ['Accept' => 'application/json'])->assertOk()->assertJsonPath('rows.0.amount', 500);
        $this->post("/api/cards/$c->id/messages/preview", ['file' => UploadedFile::fake()->create('bad.msg', 1)], $h + ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_explanation_fallback_and_provider_output_validation(): void
    {
        $c = Card::factory()->create();
        $h = $this->headers($c->user);
        config(['services.openai.api_key' => null]);
        $this->postJson('/api/recommendation/explain', [], $h)->assertOk()->assertJsonPath('source', 'deterministic');
        config(['services.openai.api_key' => 'test']);
        Http::fake(['*' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['explanations' => [['card_id' => 99999, 'reason' => 'Wrong', 'tradeoff' => 'Wrong']]])]]]]])]);
        $this->postJson('/api/recommendation/explain', [], $h)->assertJsonPath('source', 'deterministic');
        Http::swap(new Factory);
        Http::fake(['*' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['explanations' => [['card_id' => $c->id, 'reason' => 'Category fit', 'tradeoff' => 'Due date risk']]])]]]]])]);
        $this->postJson('/api/recommendation/explain', [], $h)->assertJsonPath('source', 'ai')->assertJsonPath('explanations.0.card_id', $c->id);
    }

    public function test_sync_idempotent_update_and_revision_conflict(): void
    {
        $c = Card::factory()->create();
        $h = $this->headers($c->user);
        $op = ['operation_id' => (string) Str::uuid(), 'method' => 'PUT', 'path' => "/cards/$c->id", 'payload' => ['card_name' => 'Synced'], 'revision' => $c->revision];
        $this->postJson('/api/sync/mutations', $op, $h)->assertOk()->assertJsonPath('data.card_name', 'Synced');
        $revision = $c->fresh()->revision;
        $this->postJson('/api/sync/mutations', $op, $h)->assertOk();
        $this->assertEquals($revision, $c->fresh()->revision);
        $this->assertDatabaseCount('sync_receipts', 1);
        $op['operation_id'] = (string) Str::uuid();
        $op['payload']['card_name'] = 'Conflict';
        $this->postJson('/api/sync/mutations', $op, $h)->assertConflict()->assertJsonPath('revision', $revision);
        $op['revision'] = $revision;
        $this->postJson('/api/sync/mutations', $op, $h)->assertOk();
    }

    public function test_sync_requires_revision_and_rechecks_permissions(): void
    {
        $c = Card::factory()->create();
        $u = User::factory()->create();
        $this->member($c, $u, 'editor');
        $h = $this->headers($u);
        $op = ['operation_id' => (string) Str::uuid(), 'method' => 'PUT', 'path' => "/cards/$c->id", 'payload' => ['card_name' => 'Synced']];
        $this->postJson('/api/sync/mutations', $op, $h)->assertUnprocessable();
        DB::table('card_memberships')->delete();
        $op['revision'] = 1;
        $this->postJson('/api/sync/mutations', $op, $h)->assertForbidden();
    }

    public function test_sync_marks_deletion_and_receipt_reuse_must_match(): void
    {
        $c = Card::factory()->create();
        $h = $this->headers($c->user);
        $op = ['operation_id' => (string) Str::uuid(), 'method' => 'DELETE', 'path' => "/cards/$c->id", 'payload' => [], 'revision' => 1];
        $this->postJson('/api/sync/mutations', $op, $h)->assertOk();
        $this->postJson('/api/sync/mutations', $op, $h)->assertOk();
        $this->assertDatabaseHas('sync_events', ['entity' => 'cards', 'entity_id' => $c->id, 'action' => 'delete']);
        $op['method'] = 'PUT';
        $this->postJson('/api/sync/mutations', $op, $h)->assertUnprocessable();
    }

    public function test_sync_invalid_mutation_rolls_back_without_receipt(): void
    {
        $u = User::factory()->create();
        $this->postJson('/api/sync/mutations', ['operation_id' => (string) Str::uuid(), 'method' => 'POST', 'path' => '/cards', 'payload' => []], $this->headers($u))->assertUnprocessable();
        $this->assertDatabaseCount('cards', 0);
        $this->assertDatabaseCount('sync_receipts', 0);
    }

    public function test_sync_upload_retry_does_not_repeat_processing(): void
    {
        Storage::fake('local');
        $c = Card::factory()->create();
        $h = $this->headers($c->user);
        $this->mock(StatementAnalysisService::class, fn ($m) => $m->shouldReceive('analyze')->once()->andReturn(['analyzed' => false, 'message' => 'Unavailable', 'statement' => [], 'transactions' => []]));
        $file = UploadedFile::fake()->create('statement.pdf', 10, 'application/pdf');
        $op = ['operation_id' => (string) Str::uuid(), 'method' => 'POST', 'path' => "/cards/$c->id/statements", 'payload_json' => json_encode(['billing_month' => 10, 'billing_year' => 2026]), 'revision' => 1, 'file' => $file];
        $this->post('/api/sync/mutations', $op, $h + ['Accept' => 'application/json'])->assertOk();
        $this->post('/api/sync/mutations', $op, $h + ['Accept' => 'application/json'])->assertOk();
        $this->assertDatabaseCount('statements', 1);
    }

    public function test_revision_conflict_does_not_disclose_an_inaccessible_card(): void
    {
        $card = Card::factory()->create();
        $outsider = User::factory()->create();
        $this->putJson("/api/cards/$card->id", ['card_name' => 'No', 'revision' => 999], $this->headers($outsider))->assertForbidden()->assertJsonMissing(['card_name' => $card->card_name]);
    }

    public function test_receipt_replay_rechecks_revoked_sharing(): void
    {
        $card = Card::factory()->create();
        $editor = User::factory()->create();
        $this->member($card, $editor, 'editor');
        $h = $this->headers($editor);
        $op = ['operation_id' => (string) Str::uuid(), 'method' => 'PUT', 'path' => "/cards/$card->id", 'payload' => ['card_name' => 'Updated'], 'revision' => 1];
        $this->postJson('/api/sync/mutations', $op, $h)->assertOk();
        DB::table('card_memberships')->delete();
        $this->postJson('/api/sync/mutations', $op, $h)->assertForbidden();
    }
}
