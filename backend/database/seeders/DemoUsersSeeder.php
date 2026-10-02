<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Demo User', 'email' => 'demo@example.com', 'password' => 'DemoUser@123'],
            ['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'AdminUser@123'],
        ] as $account) {
            User::firstOrCreate(['email' => $account['email']], $account);
        }
    }
}
