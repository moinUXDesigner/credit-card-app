<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('card_name');
            $table->string('bank_name');
            $table->char('last_four_digits', 4);
            $table->enum('network', ['visa', 'mastercard', 'rupay', 'amex']);
            $table->decimal('total_limit', 12, 2);
            $table->decimal('current_outstanding', 12, 2)->default(0);
            $table->unsignedTinyInteger('statement_day');
            $table->unsignedTinyInteger('due_day');
            $table->decimal('annual_fee_amount', 10, 2)->default(0);
            $table->unsignedTinyInteger('annual_fee_month');
            $table->decimal('waiver_spend_required', 12, 2)->default(0);
            $table->decimal('waiver_spend_completed', 12, 2)->default(0);
            $table->unsignedTinyInteger('card_year_start_month');
            $table->decimal('reward_point_balance', 12, 2)->default(0);
            $table->decimal('reward_point_value_estimate', 8, 4)->default(0);
            $table->json('best_categories')->nullable();
            $table->decimal('reward_rate_general', 5, 2)->nullable();
            $table->decimal('cashback_cap_amount', 10, 2)->nullable();
            $table->boolean('lounge_access')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
