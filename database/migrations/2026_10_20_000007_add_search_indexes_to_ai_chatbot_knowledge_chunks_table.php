<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('ai-chatbot.tables.knowledge_chunks'), function (Blueprint $table): void {
            $table->index(
                ['source_type', 'locale', 'source_modified_at'],
                'ai_chatbot_source_locale_modified_index',
            );
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table(config('ai-chatbot.tables.knowledge_chunks'), function (Blueprint $table): void {
                $table->fullText(['title', 'content'], 'ai_chatbot_title_content_fulltext');
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table(config('ai-chatbot.tables.knowledge_chunks'), function (Blueprint $table): void {
                $table->dropFullText('ai_chatbot_title_content_fulltext');
            });
        }

        Schema::table(config('ai-chatbot.tables.knowledge_chunks'), function (Blueprint $table): void {
            $table->dropIndex('ai_chatbot_source_locale_modified_index');
        });
    }
};
