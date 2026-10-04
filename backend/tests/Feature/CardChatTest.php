<?php

namespace Tests\Feature;

use App\Jobs\AnswerCardChat;
use App\Models\AiRequest;
use App\Models\Card;
use App\Models\ChatConversation;
use App\Models\User;
use App\Services\CardChatContextService;
use App\Services\IssuerResearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CardChatTest extends TestCase
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

    private function setupChat(): array
    {
        config(['services.openai.api_key' => 'test-key']);
        Queue::fake();
        Storage::fake('local');
        $user = User::factory()->create();
        $headers = ['Authorization' => 'Bearer '.auth('api')->login($user)];
        $card = Card::factory()->for($user)->create(['bank_name' => 'SBI Card', 'card_name' => 'BPCL OCTANE', 'current_outstanding' => 5000]);
        $conversation = $this->postJson('/api/chat/conversations', ['card_id' => $card->id], $headers)->assertCreated()->json();

        return [$user, $headers, $card, $conversation];
    }

    private function answer(string $text, array $ids = []): array
    {
        return ['status' => 'completed', 'usage' => ['input_tokens' => 10, 'output_tokens' => 20], 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['answer' => $text, 'source_ids' => $ids])]]]]];
    }

    public function test_chat_job_reads_fresh_authorized_data_and_saves_private_cited_history(): void
    {
        [$user,$headers,$card,$conversation] = $this->setupChat();
        $response = $this->postJson('/api/chat/conversations/'.$conversation['id'].'/messages', ['client_id' => (string) Str::uuid(), 'content' => 'What is my balance?'], $headers)->assertAccepted();
        $card->update(['current_outstanding' => 7499.5]);
        Http::fake(['api.openai.com/*' => Http::sequence()->push(['status' => 'completed', 'output' => [['type' => 'function_call', 'call_id' => 'call1', 'name' => 'read_cards', 'arguments' => json_encode(['card_id' => $card->id])]]])->push($this->answer('Your saved outstanding is ₹7,499.50.', ['card:'.$card->id]))]);
        (new AnswerCardChat($response->json('request_id')))->handle();
        $request = AiRequest::find($response->json('request_id'));
        $this->assertSame('ready', $request->status);
        $data = $this->getJson('/api/chat/conversations/'.$conversation['id'], $headers)->assertOk()->json();
        $this->assertCount(2, $data['messages']);
        $this->assertSame('user', $data['messages'][0]['role']);
        $this->assertStringContainsString('7,499.50', $data['messages'][1]['content']);
        $this->assertSame('/cards/'.$card->id, $data['messages'][1]['sources'][0]['url']);
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data()), '7499.5') && $r['store'] === false);
        $stranger = User::factory()->create();
        $strangerHeaders = ['Authorization' => 'Bearer '.auth('api')->login($stranger)];
        $this->getJson('/api/chat/conversations/'.$conversation['id'], $strangerHeaders)->assertNotFound();
        $this->getJson('/api/ai/requests/'.$request->id, $strangerHeaders)->assertNotFound();
        $this->deleteJson('/api/chat/conversations/'.$conversation['id'], [], $headers)->assertNoContent();
        $this->assertDatabaseCount('chat_messages', 0);
        $this->assertDatabaseCount('ai_requests', 0);
    }

    public function test_duplicate_and_failed_retry_use_the_same_message_and_only_one_active_answer(): void
    {
        [$user,$headers,$card,$conversation] = $this->setupChat();
        $url = '/api/chat/conversations/'.$conversation['id'].'/messages';
        $payload = ['client_id' => (string) Str::uuid(), 'content' => 'Explain rewards'];
        $first = $this->postJson($url, $payload, $headers)->assertAccepted();
        $this->postJson($url, $payload, $headers)->assertAccepted()->assertJsonPath('request_id', $first->json('request_id'));
        $this->postJson($url, ['client_id' => (string) Str::uuid(), 'content' => 'Another question'], $headers)->assertConflict();
        $this->postJson($url, [...$payload, 'content' => 'Changed content'], $headers)->assertUnprocessable();
        Queue::assertPushed(AnswerCardChat::class, 1);
        (new AnswerCardChat($first->json('request_id')))->failed(null);
        $this->postJson($url, $payload, $headers)->assertAccepted()->assertJsonPath('request_id', $first->json('request_id'));
        Queue::assertPushed(AnswerCardChat::class, 2);
        $this->assertDatabaseCount('chat_messages', 2);
    }

    public function test_revoked_shared_access_blocks_jobs_history_and_sources_but_allows_deletion(): void
    {
        [$user,$headers,$own,$ignored] = $this->setupChat();
        $other = User::factory()->create();
        $shared = Card::factory()->for($other)->create();
        DB::table('card_memberships')->insert(['card_id' => $shared->id, 'user_id' => $user->id, 'permission' => 'viewer', 'accepted_at' => now(), 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
        $conversation = $this->postJson('/api/chat/conversations', ['card_id' => $shared->id], $headers)->assertCreated()->json();
        $job = $this->postJson('/api/chat/conversations/'.$conversation['id'].'/messages', ['client_id' => (string) Str::uuid(), 'content' => 'Explain this card'], $headers)->assertAccepted();
        DB::table('card_memberships')->where('card_id', $shared->id)->delete();
        Http::fake();
        (new AnswerCardChat($job->json('request_id')))->handle();
        Http::assertNothingSent();
        $this->assertSame('failed', AiRequest::find($job->json('request_id'))->status);
        $this->getJson('/api/chat/conversations/'.$conversation['id'], $headers)->assertForbidden();
        $this->getJson('/api/ai/requests/'.$job->json('request_id'), $headers)->assertForbidden();
        $this->deleteJson('/api/chat/conversations/'.$conversation['id'], [], $headers)->assertNoContent();
    }

    public function test_issuer_research_never_sends_private_context_and_only_keeps_official_links(): void
    {
        [$user,$headers,$card,$conversation] = $this->setupChat();
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Official fuel benefits.', 'annotations' => [
            ['type' => 'url_citation', 'url' => 'https://www.sbicard.com/product', 'title' => 'SBI product'],
            ['type' => 'url_citation', 'url' => 'https://evil.example', 'title' => 'Untrusted'],
        ]]]]]])]);
        $result = app(IssuerResearchService::class)->research($card, 'benefits');
        $this->assertCount(1, $result['sources']);
        Http::assertSent(function ($request) use ($card) {
            $json = json_encode($request->data());

            return ! str_contains($json, '5000') && ! str_contains($json, $card->last_four_digits) && $request['tools'][0]['filters']['allowed_domains'] === ['sbicard.com'];
        });
    }

    public function test_selected_pdf_is_read_with_pending_status_and_does_not_change_data(): void
    {
        [$user,$headers,$card,$ignored] = $this->setupChat();
        Storage::disk('local')->put('statement.pdf', '%PDF-1.4 fixture');
        $statement = $card->statements()->create(['file_path' => 'statement.pdf', 'original_filename' => 'statement.pdf', 'billing_month' => 10, 'billing_year' => 2026, 'analysis_status' => 'pending']);
        $conversation = $this->postJson('/api/chat/conversations', ['card_id' => $card->id, 'statement_id' => $statement->id], $headers)->assertCreated()->json();
        $job = $this->postJson('/api/chat/conversations/'.$conversation['id'].'/messages', ['client_id' => (string) Str::uuid(), 'content' => 'Explain this PDF'], $headers);
        Http::fake(['api.openai.com/*' => Http::response($this->answer('This PDF awaits import review.', ['statement:'.$statement->id]))]);
        (new AnswerCardChat($job->json('request_id')))->handle();
        $this->assertSame('ready', AiRequest::find($job->json('request_id'))->status);
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data()), 'input_file') && str_contains(json_encode($r->data()), 'Import status: pending'));
        $this->assertSame('pending', $statement->fresh()->analysis_status);
        $this->assertEquals(5000, $card->fresh()->current_outstanding);
    }

    public function test_spending_tools_compute_totals_and_enforce_card_scope(): void
    {
        [$user,$headers,$card,$data] = $this->setupChat();
        $card->transactions()->create(['transaction_date' => '2026-10-01', 'description' => 'Fuel', 'amount' => 1000, 'category' => 'fuel', 'direction' => 'purchase']);
        $card->transactions()->create(['transaction_date' => '2026-10-02', 'description' => 'Refund', 'amount' => 100, 'category' => 'fuel', 'direction' => 'credit']);
        $card->spendEntries()->create(['year' => 2026, 'month' => 10, 'category' => 'fuel', 'manual_amount' => 200, 'amount_spent' => 1200]);
        $service = app(CardChatContextService::class);
        $conversation = ChatConversation::find($data['id']);
        $cards = $service->cards($user, $conversation);
        $result = $service->tool('spending', ['from' => '2026-10-01', 'to' => '2026-10-31'], $cards);
        $this->assertSame(1000.0, $result['purchase_total']);
        $this->assertSame(100.0, $result['credits_total']);
        $this->assertSame(200.0, $result['manual_monthly_amounts']['fuel']);
        $this->expectException(\RuntimeException::class);
        $service->tool('read_cards', ['card_id' => 999999], $cards);
    }

    public function test_unconfigured_chat_fails_without_creating_messages(): void
    {
        [$user,$headers,$card,$conversation] = $this->setupChat();
        config(['services.openai.api_key' => null]);
        $this->postJson('/api/chat/conversations/'.$conversation['id'].'/messages',['client_id' => (string) Str::uuid(), 'content' => 'Help'],$headers)->assertStatus(503);
        $this->assertDatabaseCount('chat_messages',0);
    }
}
