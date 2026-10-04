<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create();
        $card = Card::factory()->for($user)->create(['current_outstanding' => 5000]);
        $statement = $card->statements()->create(['file_path' => 'test.pdf', 'original_filename' => 'test.pdf', 'billing_month' => 10, 'billing_year' => 2026, 'analysis_status' => 'completed']);
        $row = $card->transactions()->create(['statement_id' => $statement->id, 'transaction_date' => '2026-09-03', 'description' => 'Petrol shop', 'amount' => 1000, 'category' => 'other', 'direction' => 'purchase']);

        return [$user, $card, $statement, $row];
    }

    public function test_category_change_persists_and_moves_imported_spend_without_changing_manual_spend_or_balance(): void
    {
        [$user, $card, $statement, $row] = $this->fixture();
        $card->spendEntries()->create(['year' => 2026, 'month' => 9, 'category' => 'other', 'manual_amount' => 200, 'amount_spent' => 1200]);
        $headers = ['Authorization' => 'Bearer '.auth('api')->login($user)];
        $url = "/api/statements/{$statement->id}/transactions/{$row->id}/category";
        $this->patchJson($url, ['category' => 'fuel'], $headers)->assertOk()->assertJsonPath('category', 'fuel');
        $this->assertSame('fuel', $row->fresh()->category);
        $this->assertEquals(200, $card->spendEntries()->where('category', 'other')->first()->amount_spent);
        $this->assertEquals(1000, $card->spendEntries()->where('category', 'fuel')->first()->amount_spent);
        $this->assertEquals(5000, $card->fresh()->current_outstanding);
        $this->patchJson($url, ['category' => 'invalid'], $headers)->assertUnprocessable();
        $this->assertSame('fuel', $row->fresh()->category);
    }

    public function test_transaction_must_belong_to_the_statement(): void
    {
        [$user, $card, $statement, $row] = $this->fixture();
        $other = $card->statements()->create(['file_path' => 'other.pdf', 'original_filename' => 'other.pdf', 'billing_month' => 8, 'billing_year' => 2026]);
        $this->patchJson("/api/statements/{$other->id}/transactions/{$row->id}/category", ['category' => 'fuel'], ['Authorization' => 'Bearer '.auth('api')->login($user)])->assertNotFound();
        $this->assertSame('other', $row->fresh()->category);
    }

    public function test_viewer_cannot_change_categories(): void
    {
        [$owner, $card, $statement, $row] = $this->fixture();
        $viewer = User::factory()->create();
        DB::table('card_memberships')->insert(['card_id' => $card->id, 'user_id' => $viewer->id, 'permission' => 'viewer', 'accepted_at' => now(), 'expires_at' => now()->addWeek(), 'created_at' => now(), 'updated_at' => now()]);
        $this->patchJson("/api/statements/{$statement->id}/transactions/{$row->id}/category", ['category' => 'fuel'], ['Authorization' => 'Bearer '.auth('api')->login($viewer)])->assertForbidden();
        $this->assertSame('other', $row->fresh()->category);
    }
}
