<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('user');
            $t->timestamp('suspended_at')->nullable();
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('target_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action');
            $t->timestamps();
        });
        Schema::create('card_memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('card_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('permission');
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->unique(['card_id', 'user_id']);
        });
        foreach (['cards', 'benefits', 'statements', 'monthly_spend_entries'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('revision')->default(1));
        }
        Schema::table('monthly_spend_entries', fn (Blueprint $t) => $t->decimal('manual_amount', 12, 2)->default(0));
        DB::table('monthly_spend_entries')->update(['manual_amount' => DB::raw('amount_spent')]);
        Schema::table('transactions', function (Blueprint $t) {
            $t->foreignId('statement_id')->nullable()->change();
            $t->string('direction')->default('purchase');
            $t->string('source')->default('pdf');
            $t->string('fingerprint', 64)->nullable()->index();
        });
        // Existing PDF-derived aggregates must not be counted again as manual spend.
        foreach (DB::table('monthly_spend_entries')->get() as $entry) {
            $derived = DB::table('transactions')->where('card_id', $entry->card_id)->where('category', $entry->category)->whereYear('transaction_date', $entry->year)->whereMonth('transaction_date', $entry->month)->sum('amount');
            DB::table('monthly_spend_entries')->where('id', $entry->id)->update(['manual_amount' => max(0, (float) $entry->amount_spent - (float) $derived)]);
        }
        Schema::create('message_imports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('card_id')->constrained()->cascadeOnDelete();
            $t->string('fingerprint', 64);
            $t->timestamps();
            $t->unique(['card_id', 'fingerprint']);
        });
        Schema::create('message_previews', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('card_id')->constrained()->cascadeOnDelete();
            $t->string('fingerprint', 64);
            $t->json('summary');
            $t->timestamp('expires_at');
        });
        Schema::create('sync_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('entity');
            $t->unsignedBigInteger('entity_id');
            $t->string('action');
            $t->unsignedBigInteger('revision')->nullable();
            $t->timestamp('created_at');
            $t->index(['user_id', 'id']);
        });
        Schema::create('sync_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->uuid('operation_id');
            $t->string('request_hash', 64);
            $t->json('response');
            $t->timestamps();
            $t->unique(['user_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        foreach (['sync_receipts', 'sync_events', 'message_previews', 'message_imports', 'card_memberships', 'audit_events'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('transactions', fn (Blueprint $t) => $t->dropColumn(['direction', 'source', 'fingerprint']));
        Schema::table('monthly_spend_entries', fn (Blueprint $t) => $t->dropColumn('manual_amount'));
        foreach (['cards', 'benefits', 'statements', 'monthly_spend_entries'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('revision'));
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'suspended_at']));
    }
};
