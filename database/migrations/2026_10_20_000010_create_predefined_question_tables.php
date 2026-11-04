<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chatbot_question_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title_en');
            $table->string('title_ar');
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('ai_chatbot_predefined_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('ai_chatbot_question_categories')->cascadeOnDelete();
            $table->text('question_en');
            $table->text('question_ar');
            $table->longText('answer_en');
            $table->longText('answer_ar');
            $table->string('source_url', 2048)->nullable();
            $table->nullableMorphs('cms_page');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['category_id', 'is_active', 'sort_order'], 'ai_chatbot_questions_listing_index');
        });

        Schema::create('ai_chatbot_question_followups', function (Blueprint $table): void {
            $table->foreignId('question_id')->constrained('ai_chatbot_predefined_questions')->cascadeOnDelete();
            $table->foreignId('followup_question_id')->constrained('ai_chatbot_predefined_questions')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['question_id', 'followup_question_id'], 'ai_chatbot_question_followups_primary');
            $table->index(['question_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chatbot_question_followups');
        Schema::dropIfExists('ai_chatbot_predefined_questions');
        Schema::dropIfExists('ai_chatbot_question_categories');
    }
};
