<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('ai-chatbot.tables.settings'), function (Blueprint $table): void {
            $table->id();
            $table->string('scope_type')->default('global');
            $table->string('scope_id')->default('global');
            $table->string('key');
            $table->json('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();

            $table->unique(['scope_type', 'scope_id', 'key'], 'ai_chatbot_setting_scope_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('ai-chatbot.tables.settings'));
    }
};
