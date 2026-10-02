<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigrationUpgradeTest extends TestCase
{
    public function test_populated_database_is_preserved_by_additive_upgrade(): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.upgrade' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('upgrade');
        try {
            $files = glob(database_path('migrations/*.php'));
            $new = array_pop($files);
            foreach ($files as $file) {
                (require $file)->up();
            }
            $user = User::factory()->make()->getAttributes();
            unset($user['role']);
            $userId = DB::table('users')->insertGetId($user);
            $card = Card::factory()->make(['user_id' => $userId])->getAttributes();
            unset($card['revision']);
            $cardId = DB::table('cards')->insertGetId($card);
            DB::table('monthly_spend_entries')->insert(['card_id' => $cardId, 'year' => 2026, 'month' => 10, 'category' => 'other', 'amount_spent' => 123.45]);
            (require $new)->up();
            $this->assertEquals('user', DB::table('users')->where('id', $userId)->value('role'));
            $this->assertEquals($userId, DB::table('cards')->where('id', $cardId)->value('user_id'));
            $this->assertEquals(123.45, (float) DB::table('monthly_spend_entries')->value('manual_amount'));
            $this->assertEquals(123.45, (float) DB::table('monthly_spend_entries')->value('amount_spent'));
            $this->assertEquals(1, DB::table('cards')->value('revision'));
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('upgrade');
        }
    }
}
