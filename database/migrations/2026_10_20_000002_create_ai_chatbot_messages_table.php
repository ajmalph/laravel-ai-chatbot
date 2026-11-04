<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('ai-chatbot.tables.messages'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')
                ->constrained(config('ai-chatbot.tables.conversations'))
                ->cascadeOnDelete();
            $table->string('role', 32);
            $table->longText('content')->nullable();
            $table->string('status', 32)->default('completed');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('ai-chatbot.tables.messages'));
    }
};
