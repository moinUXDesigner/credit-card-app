<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CardImportTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = auth('api')->login($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    private function cardRow(array $overrides = []): array
    {
        return array_merge([
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
            'card_year_start_month' => 9,
        ], $overrides);
    }

    public function test_json_import_creates_cards_for_valid_rows(): void
    {
        $user = User::factory()->create();
        $rows = [
            $this->cardRow(['card_name' => 'Regalia']),
            $this->cardRow(['card_name' => 'Millennia', 'last_four_digits' => '5678']),
        ];
        $file = UploadedFile::fake()->createWithContent('cards.json', json_encode($rows));

        $response = $this->postJson('/api/cards/import', ['file' => $file], $this->authHeaders($user))
            ->assertStatus(201);

        $response->assertJson(['imported' => 2, 'failed' => 0]);
        $this->assertDatabaseHas('cards', ['card_name' => 'Regalia', 'user_id' => $user->id]);
        $this->assertDatabaseHas('cards', ['card_name' => 'Millennia', 'user_id' => $user->id]);
    }

    public function test_json_import_reports_invalid_rows_without_blocking_valid_ones(): void
    {
        $user = User::factory()->create();
        $rows = [
            $this->cardRow(['card_name' => 'Regalia']),
            $this->cardRow(['card_name' => '', 'last_four_digits' => '999']), // invalid: blank name + bad digits
        ];
        $file = UploadedFile::fake()->createWithContent('cards.json', json_encode($rows));

        $response = $this->postJson('/api/cards/import', ['file' => $file], $this->authHeaders($user))
            ->assertStatus(201);

        $response->assertJson(['imported' => 1, 'failed' => 1]);
        $this->assertSame(2, $response->json('errors.0.row'));
        $this->assertDatabaseCount('cards', 1);
    }

    public function test_json_import_rejects_non_array_root(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('cards.json', json_encode(['cards' => [$this->cardRow()]]));

        $this->postJson('/api/cards/import', ['file' => $file], $this->authHeaders($user))
            ->assertStatus(422);
    }

    public function test_csv_import_parses_header_row_and_best_categories_list(): void
    {
        $user = User::factory()->create();
        $csv = "card_name,bank_name,last_four_digits,network,total_limit,current_outstanding,statement_day,due_day,annual_fee_amount,annual_fee_month,waiver_spend_required,card_year_start_month,best_categories,lounge_access\n"
            ."Regalia,HDFC,1234,visa,200000,35000,5,25,2500,9,300000,9,\"fuel;dining\",yes\n";
        $file = UploadedFile::fake()->createWithContent('cards.csv', $csv);

        $response = $this->postJson('/api/cards/import', ['file' => $file], $this->authHeaders($user))
            ->assertStatus(201);

        $response->assertJson(['imported' => 1, 'failed' => 0]);
        $this->assertDatabaseHas('cards', ['card_name' => 'Regalia', 'lounge_access' => true]);
        $this->assertSame(['fuel', 'dining'], \App\Models\Card::first()->best_categories);
    }

    public function test_xlsx_import_creates_card(): void
    {
        $user = User::factory()->create();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = array_keys($this->cardRow());
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray(array_values($this->cardRow()), null, 'A2');

        $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($spreadsheet))->save($tmpPath);
        $file = new UploadedFile($tmpPath, 'cards.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->postJson('/api/cards/import', ['file' => $file], $this->authHeaders($user))
            ->assertStatus(201);

        $response->assertJson(['imported' => 1, 'failed' => 0]);
        $this->assertDatabaseHas('cards', ['card_name' => 'Regalia', 'user_id' => $user->id]);
    }

    public function test_import_rejects_shared_limit_group_mismatch_within_batch(): void
    {
        $user = User::factory()->create();
        $rows = [
            $this->cardRow(['card_name' => 'Regalia', 'shared_limit_group' => 'hdfc-combined', 'total_limit' => 100000]),
            $this->cardRow(['card_name' => 'Millennia', 'last_four_digits' => '5678', 'shared_limit_group' => 'hdfc-combined', 'total_limit' => 150000]),
        ];
        $file = UploadedFile::fake()->createWithContent('cards.json', json_encode($rows));

        $response = $this->postJson('/api/cards/import', ['file' => $file], $this->authHeaders($user))
            ->assertStatus(201);

        $response->assertJson(['imported' => 1, 'failed' => 1]);
        $this->assertDatabaseCount('cards', 1);
    }

    public function test_import_rejects_unsupported_file_type(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('cards.pdf', 10);

        $this->postJson('/api/cards/import', ['file' => $file], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('cards.json', json_encode([$this->cardRow()]));

        $this->postJson('/api/cards/import', ['file' => $file])->assertStatus(401);
    }
}
