<?php

namespace Tests\Feature;

use App\Jobs\ExtractStatementPdf;
use App\Models\AiRequest;
use App\Models\Card;
use App\Models\StatementPreview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiPdfWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function setupAi(): array
    {
        config(['services.openai.api_key' => 'test-key']);
        Queue::fake();
        Storage::fake('local');
        $user = User::factory()->create();

        return [$user, ['Authorization' => 'Bearer '.auth('api')->login($user)]];
    }

    private function response(array $override = []): array
    {
        $fields = ['card_name', 'bank_name', 'last_four_digits', 'network', 'statement_date', 'due_date', 'total_due', 'minimum_due', 'credit_limit', 'reward_point_balance', 'annual_fee_amount', 'annual_fee_month', 'waiver_spend_required', 'reward_rate_general', 'cashback_cap_amount', 'forex_markup_percent', 'fuel_surcharge_waiver_percent', 'insurance_cover_amount'];
        $data = [...array_fill_keys($fields, null), 'card_name' => 'BPCL SBI Card OCTANE', 'bank_name' => 'SBI Card', 'statement_date' => '2026-10-01', 'due_date' => '2026-10-21', 'total_due' => 22471, 'minimum_due' => 449, 'credit_limit' => 391000, 'reward_point_balance' => 18791, 'transactions' => [['date' => '2026-09-03', 'description' => 'Pharmacy', 'amount' => 100, 'direction' => 'purchase', 'category' => 'medicines']], ...$override];

        return ['status' => 'completed', 'usage' => ['input_tokens' => 10, 'output_tokens' => 20], 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($data)]]]]];
    }

    public function test_upload_queues_one_extraction_shared_with_add_card_without_mutating_card(): void
    {
        [$user,$headers] = $this->setupAi();
        Http::fake(['api.openai.com/*' => Http::response($this->response())]);
        $card = Card::factory()->for($user)->create(['current_outstanding' => 100]);
        $response = $this->postJson('/api/cards/analyze', ['file' => UploadedFile::fake()->create('statement.pdf', 10, 'application/pdf')], $headers)->assertAccepted()->assertJsonPath('status', 'processing');
        Http::assertNothingSent();
        Queue::assertPushed(ExtractStatementPdf::class, 1);
        $id = $response->json('preview_id');
        $request = AiRequest::where('preview_id', $id)->firstOrFail();
        (new ExtractStatementPdf($request->id))->handle();
        $this->getJson('/api/statement-previews/'.$id, $headers)->assertOk()->assertJsonPath('status', 'ready')->assertJsonPath('source', 'ai')->assertJsonPath('summary.minimum_due', 449)->assertJsonPath('card.card_name', 'BPCL SBI Card OCTANE')->assertJsonPath('card.reward_point_balance', 18791);
        $this->assertEquals(100, $card->fresh()->current_outstanding);
        $this->assertDatabaseCount('statements', 0);
        $this->assertSame(20, $request->fresh()->usage['output_tokens']);
        Http::assertSent(fn ($r) => $r['input'][1]['content'][0]['type'] === 'input_file' && ! isset($r['tools']) && $r['store'] === false);
    }

    public function test_provider_failure_uses_local_fallback_and_leaves_identity_unknown(): void
    {
        [$user,$headers] = $this->setupAi();
        Http::fake(['api.openai.com/*' => Http::response([], 503)]);
        Process::fake(['*pdftotext*' => Process::result(output: "SBI Card\nCard Number XXXX XXXX XXXX XX45\nStatement Date: 01 Oct 2026\nTotal Amount Due: 22471.00\nReward Points Balance: 18791")]);
        $response = $this->postJson('/api/statement-previews', ['file' => UploadedFile::fake()->create('statement.pdf', 10, 'application/pdf')], $headers)->assertAccepted();
        $request = AiRequest::where('preview_id', $response->json('preview_id'))->firstOrFail();
        (new ExtractStatementPdf($request->id))->handle();
        $this->getJson('/api/statement-previews/'.$response->json('preview_id'), $headers)->assertOk()->assertJsonPath('status', 'ready')->assertJsonPath('source', 'local')->assertJsonPath('summary.last_four_digits', null)->assertJsonPath('summary.total_due', 22471);
    }

    public function test_invalid_ai_numbers_fall_back_and_unreadable_pdf_is_failed(): void
    {
        [$user,$headers] = $this->setupAi();
        Http::fake(['api.openai.com/*' => Http::response($this->response(['minimum_due' => -1]))]);
        Process::fake(['*pdftotext*' => Process::result(output: '')]);
        $response = $this->postJson('/api/statement-previews', ['file' => UploadedFile::fake()->create('statement.pdf', 10, 'application/pdf')], $headers);
        $request = AiRequest::where('preview_id', $response->json('preview_id'))->firstOrFail();
        (new ExtractStatementPdf($request->id))->handle();
        $this->assertSame('failed', $request->fresh()->status);
        $this->assertFalse(StatementPreview::find($request->preview_id)->result['analyzed']);
    }

    public function test_job_and_polling_recheck_revoked_access(): void
    {
        [$user,$headers] = $this->setupAi();
        Http::fake();
        $card = Card::factory()->for($user)->create();
        $response = $this->postJson('/api/statement-previews', ['card_id' => $card->id, 'file' => UploadedFile::fake()->create('statement.pdf', 10, 'application/pdf')], $headers);
        $request = AiRequest::where('preview_id', $response->json('preview_id'))->firstOrFail();
        $card->user_id = User::factory()->create()->id;
        $card->save();
        (new ExtractStatementPdf($request->id))->handle();
        $this->assertSame('failed', $request->fresh()->status);
        Http::assertNothingSent();
        $this->getJson('/api/statement-previews/'.$request->preview_id,$headers)->assertForbidden();
    }
}
