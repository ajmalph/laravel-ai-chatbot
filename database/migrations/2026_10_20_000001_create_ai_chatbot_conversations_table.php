<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('ai-chatbot.tables.conversations'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('participant_type')->nullable();
            $table->string('participant_id')->nullable();
            $table->string('title')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['participant_type', 'participant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('ai-chatbot.tables.conversations'));
    }
};
