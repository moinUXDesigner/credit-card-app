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
        // Two separate ALTER TABLE statements: MySQL's InnoDB requires an
        // index covering card_id to exist at all times to support the
        // card_id foreign key, so the replacement index must be created
        // before the old one is dropped (combining them in one statement
        // errors with "needed in a foreign key constraint").
        Schema::table('monthly_spend_entries', function (Blueprint $table) {
            $table->unique(['card_id', 'year', 'month', 'category']);
        });

        Schema::table('monthly_spend_entries', function (Blueprint $table) {
            $table->dropUnique(['card_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_spend_entries', function (Blueprint $table) {
            $table->unique(['card_id', 'year', 'month']);
        });

        Schema::table('monthly_spend_entries', function (Blueprint $table) {
            $table->dropUnique(['card_id', 'year', 'month', 'category']);
        });
    }
};
