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
        Schema::table('cards', function (Blueprint $table) {
            $table->decimal('forex_markup_percent', 5, 2)->nullable()->after('cashback_cap_amount');
            $table->decimal('fuel_surcharge_waiver_percent', 5, 2)->nullable()->after('forex_markup_percent');
            $table->decimal('insurance_cover_amount', 12, 2)->nullable()->after('fuel_surcharge_waiver_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['forex_markup_percent', 'fuel_surcharge_waiver_percent', 'insurance_cover_amount']);
        });
    }
};
