<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Pgs\LaravelAiChatbot\Admin\SettingsService;
use Pgs\LaravelAiChatbot\Http\Resources\PredefinedQuestionResource;
use Pgs\LaravelAiChatbot\Http\Resources\QuestionCategoryResource;
use Pgs\LaravelAiChatbot\Models\PredefinedQuestion;
use Pgs\LaravelAiChatbot\Models\QuestionCategory;

final class PredefinedQuestionApiController
{
    public function config(Request $request, SettingsService $settings): JsonResponse
    {
        $locale = $this->locale($request);
        $values = $settings->all();

        return response()->json(['data' => ['title' => $values["screen_title_{$locale}"], 'opening_message' => $values["welcome_{$locale}"], 'fallback_response' => $values["fallback_{$locale}"], 'default_followup_prompts' => $values["default_followups_{$locale}"], 'predefined_questions_enabled' => (bool) $values['predefined_questions_enabled']]]);
    }

    public function categories(Request $request): AnonymousResourceCollection
    {
        $this->locale($request);
        if (! config('ai-chatbot.widget.predefined_questions_enabled', true)) {
            return QuestionCategoryResource::collection(collect());
        }
        $categories = QuestionCategory::query()->active()->withCount(['questions' => fn ($q) => $q->where('is_active', true)])->orderBy('sort_order')->orderBy('id')->get();

        return QuestionCategoryResource::collection($categories);
    }

    public function categoryQuestions(Request $request, QuestionCategory $category): AnonymousResourceCollection
    {
        $this->locale($request);
        abort_unless(config('ai-chatbot.widget.predefined_questions_enabled', true), 404);
        abort_unless($category->is_active, 404);

        return PredefinedQuestionResource::collection($category->questions()->published()->orderBy('sort_order')->orderBy('id')->get());
    }

    public function question(Request $request, int $question): PredefinedQuestionResource
    {
        $this->locale($request);
        abort_unless(config('ai-chatbot.widget.predefined_questions_enabled', true), 404);
        $record = PredefinedQuestion::query()->published()->with(['followups' => fn ($q) => $q->published()->select('ai_chatbot_predefined_questions.id', 'category_id', 'question_en', 'question_ar', 'answer_en', 'answer_ar', 'source_url')])->findOrFail($question);

        return new PredefinedQuestionResource($record);
    }

    private function locale(Request $request): string
    {
        $locale = $request->string('locale', app()->getLocale())->lower()->value();
        abort_unless(in_array($locale, ['en', 'ar'], true), 422, 'The locale must be en or ar.');
        app()->setLocale($locale);

        return $locale;
    }
}
