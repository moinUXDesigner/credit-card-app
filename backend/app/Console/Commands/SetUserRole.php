<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetUserRole extends Command
{
    protected $signature = 'users:role {email} {role : user or admin}';

    protected $description = 'Explicitly assign an account role and audit the change';

    public function handle(): int
    {
        if (! in_array($this->argument('role'), ['user', 'admin'], true)) {
            $this->error('Role must be user or admin.');

            return self::FAILURE;
        }
        DB::transaction(function () {
            User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            $user = User::where('email', $this->argument('email'))->lockForUpdate()->firstOrFail();
            if ($user->role === 'admin' && ! $user->suspended_at && $this->argument('role') === 'user' && User::where('role', 'admin')->whereNull('suspended_at')->count() <= 1) {
                throw new \RuntimeException('Cannot demote the last active admin.');
            }
            $user->role = $this->argument('role');
            $user->save();
            DB::table('audit_events')->insert(['target_id' => $user->id, 'action' => 'role:'.$user->role, 'created_at' => now(), 'updated_at' => now()]);
        });
        $this->info('Role updated.');

        return self::SUCCESS;
    }
}
