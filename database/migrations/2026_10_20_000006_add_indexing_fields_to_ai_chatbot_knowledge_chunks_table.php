<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('ai-chatbot.tables.knowledge_chunks'), function (Blueprint $table): void {
            $table->dropUnique('ai_chatbot_source_chunk_unique');
            $table->string('locale', 8)->default('en')->after('external_id');
            $table->text('title')->nullable()->after('chunk_index');
            $table->text('url')->nullable()->after('content');
            $table->timestamp('source_modified_at')->nullable()->index()->after('content_hash');
            $table->uuid('sync_token')->nullable()->index()->after('source_modified_at');
            $table->unique(
                ['source_type', 'source_id', 'locale', 'chunk_index'],
                'ai_chatbot_source_chunk_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table(config('ai-chatbot.tables.knowledge_chunks'), function (Blueprint $table): void {
            $table->dropUnique('ai_chatbot_source_chunk_unique');
            $table->dropIndex(['source_modified_at']);
            $table->dropIndex(['sync_token']);
            $table->dropColumn(['locale', 'title', 'url', 'source_modified_at', 'sync_token']);
            $table->unique(
                ['source_type', 'source_id', 'chunk_index'],
                'ai_chatbot_source_chunk_unique',
            );
        });
    }
};
