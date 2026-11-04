<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('ai-chatbot.tables.knowledge_chunks'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('external_id')->nullable();
            $table->unsignedInteger('chunk_index')->default(0);
            $table->longText('content');
            $table->string('content_hash', 64)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->unique(['source_type', 'source_id', 'chunk_index'], 'ai_chatbot_source_chunk_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('ai-chatbot.tables.knowledge_chunks'));
    }
};
