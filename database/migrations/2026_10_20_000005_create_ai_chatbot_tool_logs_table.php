<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('ai-chatbot.tables.tool_logs'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->nullable()
                ->constrained(config('ai-chatbot.tables.conversations'))
                ->nullOnDelete();
            $table->foreignUuid('message_id')->nullable()
                ->constrained(config('ai-chatbot.tables.messages'))
                ->nullOnDelete();
            $table->string('tool');
            $table->string('status', 32)->index();
            $table->json('arguments')->nullable();
            $table->longText('result')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('ai-chatbot.tables.tool_logs'));
    }
};
