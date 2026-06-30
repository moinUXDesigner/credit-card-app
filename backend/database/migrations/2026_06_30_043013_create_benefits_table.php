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
        Schema::create('benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['lounge', 'cashback', 'reward_points', 'dining', 'movie', 'other']);
            $table->string('title');
            $table->enum('frequency', ['monthly', 'quarterly', 'yearly', 'one_time']);
            $table->unsignedInteger('total_allowed');
            $table->unsignedInteger('used_count')->default(0);
            $table->date('expiry_date')->nullable();
            $table->decimal('estimated_value', 10, 2)->nullable();
            $table->date('cycle_start_date')->nullable();
            $table->timestamps();

            $table->index('card_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('benefits');
    }
};
