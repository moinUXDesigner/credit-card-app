<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100)->default('New conversation');
            $table->foreignId('card_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('statement_id')->nullable()->constrained()->nullOnDelete();
            $table->json('referenced_card_ids')->nullable();
            $table->timestamps();
        });
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->uuid('client_id');
            $table->string('role', 20);
            $table->unsignedInteger('position')->default(0);
            $table->string('status', 20)->default('processing');
            $table->text('content')->nullable();
            $table->json('sources')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'client_id', 'role']);
        });
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('status', 20)->default('processing');
            $table->foreignUuid('preview_id')->nullable()->constrained('statement_previews')->cascadeOnDelete();
            $table->foreignUuid('message_id')->nullable()->constrained('chat_messages')->cascadeOnDelete();
            $table->json('result')->nullable();
            $table->json('usage')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
