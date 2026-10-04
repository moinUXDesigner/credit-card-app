<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->json('summary_provenance')->nullable();
        });
        Schema::table('statements', function (Blueprint $table) {
            $table->string('fingerprint', 64)->nullable();
            $table->uuid('preview_id')->nullable();
            $table->json('extracted_summary')->nullable();
            $table->json('summary_previous')->nullable();
            $table->unique(['card_id', 'fingerprint']);
        });
        Schema::create('statement_previews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('statement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('fingerprint', 64);
            $table->json('result');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
        Schema::create('statement_import_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('operation_id');
            $table->string('request_hash', 64);
            $table->foreignId('statement_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_import_receipts');
        Schema::dropIfExists('statement_previews');
        Schema::table('statements', function (Blueprint $table) {
            $table->dropUnique(['card_id', 'fingerprint']);
            $table->dropColumn(['fingerprint', 'preview_id', 'extracted_summary', 'summary_previous']);
        });
        Schema::table('cards', fn (Blueprint $table) => $table->dropColumn('summary_provenance'));
    }
};
