<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('ai-chatbot.tables.conversations'), function (Blueprint $table): void {
            $table->string('visitor_id_hash', 64)->nullable()->index()->after('participant_id');
            $table->string('credential_hash')->nullable()->after('visitor_id_hash');
            $table->timestamp('expires_at')->nullable()->index()->after('last_message_at');
        });

        Schema::table(config('ai-chatbot.tables.messages'), function (Blueprint $table): void {
            $table->string('client_request_id', 100)->nullable()->after('conversation_id');
            $table->uuid('response_to_message_id')->nullable()->index()->after('client_request_id');
            $table->unique(['conversation_id', 'client_request_id'], 'ai_chatbot_message_request_unique');
        });
    }

    public function down(): void
    {
        Schema::table(config('ai-chatbot.tables.messages'), function (Blueprint $table): void {
            $table->dropUnique('ai_chatbot_message_request_unique');
            $table->dropIndex(['response_to_message_id']);
            $table->dropColumn(['client_request_id', 'response_to_message_id']);
        });

        Schema::table(config('ai-chatbot.tables.conversations'), function (Blueprint $table): void {
            $table->dropIndex(['visitor_id_hash']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['visitor_id_hash', 'credential_hash', 'expires_at']);
        });
    }
};
