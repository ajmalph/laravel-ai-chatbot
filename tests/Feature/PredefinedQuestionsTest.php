<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Pgs\LaravelAiChatbot\Database\Seeders\PredefinedQuestionCategorySeeder;
use Pgs\LaravelAiChatbot\Models\PredefinedQuestion;
use Pgs\LaravelAiChatbot\Models\QuestionCategory;
use Pgs\LaravelAiChatbot\Tests\TestCase;

final class PredefinedQuestionsTest extends TestCase
{
    private GenericUser $admin;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('b', 32)));
        $app['config']->set('ai-chatbot.admin.enabled', true);
        $app['config']->set('ai-chatbot.admin.authentication_middleware', ['web']);
        $app['config']->set('ai-chatbot.admin.authorization_middleware', [SubstituteBindings::class]);
        $app['config']->set('ai-chatbot.predefined.routes.middleware', ['api']);
        $app['config']->set('ai-chatbot.routes.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = new GenericUser(['id' => 5, 'name' => 'Question admin']);
        Gate::define('ai-chatbot.admin.questions.view', fn ($user): bool => (int) $user->getAuthIdentifier() === 5);
        Gate::define('ai-chatbot.admin.questions.manage', fn ($user): bool => (int) $user->getAuthIdentifier() === 5);
    }

    public function test_default_categories_are_seeded_idempotently_without_overwriting_changes(): void
    {
        $this->seed(PredefinedQuestionCategorySeeder::class);
        QuestionCategory::query()->first()->update(['title_en' => 'Administrator title']);
        $this->seed(PredefinedQuestionCategorySeeder::class);
        $this->assertSame(6, QuestionCategory::query()->count());
        $this->assertSame('Administrator title', QuestionCategory::query()->first()->title_en);
    }

    public function test_public_api_localizes_orders_and_filters_inactive_content(): void
    {
        $inactive = QuestionCategory::query()->create($this->category(['slug' => 'inactive', 'is_active' => false]));
        $active = QuestionCategory::query()->create($this->category(['slug' => 'active', 'sort_order' => 2]));
        PredefinedQuestion::query()->create($this->question($inactive, ['question_en' => 'Hidden category']));
        PredefinedQuestion::query()->create($this->question($active, ['question_en' => 'Hidden question', 'is_active' => false]));
        $visible = PredefinedQuestion::query()->create($this->question($active, ['question_en' => 'Visible', 'question_ar' => 'مرئي', 'sort_order' => 2]));
        $followup = PredefinedQuestion::query()->create($this->question($active, ['question_en' => 'Next', 'question_ar' => 'التالي', 'sort_order' => 1]));
        $visible->followups()->attach($followup, ['sort_order' => 1]);
        $followup->followups()->attach($visible, ['sort_order' => 1]);

        $this->getJson('/api/v1/chatbot/categories?locale=ar')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'عنوان');
        $this->getJson("/api/v1/chatbot/categories/{$active->id}/questions?locale=en")->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.question', 'Next');
        $this->getJson("/api/v1/chatbot/questions/{$visible->id}?locale=ar")->assertOk()->assertJsonPath('data.question', 'مرئي')->assertJsonPath('data.followups.0.question', 'التالي')->assertJsonMissing(['cms_page_type' => null]);
        $this->getJson('/api/v1/chatbot/categories/'.$inactive->id.'/questions')->assertNotFound();
    }

    public function test_category_admin_can_create_reorder_update_and_delete(): void
    {
        $url = '/admin/ai-chatbot/question-categories';
        $payload = $this->category(['slug' => 'managed', 'sort_order' => 8]);
        $this->actingAs($this->admin)->post($url, $payload)->assertRedirect();
        $category = QuestionCategory::query()->where('slug', 'managed')->firstOrFail();
        $this->actingAs($this->admin)->post("{$url}/reorder", ['positions' => [$category->id => 2]])->assertRedirect();
        $this->assertSame(2, $category->refresh()->sort_order);
        $payload['title_en'] = 'Updated';
        $payload['is_active'] = false;
        $this->actingAs($this->admin)->put("{$url}/{$category->id}", $payload)->assertRedirect();
        $this->assertFalse($category->refresh()->is_active);
        $this->actingAs($this->admin)->delete("{$url}/{$category->id}", ['confirm' => '1'])->assertRedirect();
        $this->assertModelMissing($category);
    }

    public function test_admin_crud_validation_followups_and_authorization(): void
    {
        $category = QuestionCategory::query()->create($this->category());
        $followup = PredefinedQuestion::query()->create($this->question($category));
        $url = '/admin/ai-chatbot/predefined-questions';
        $this->get($url)->assertForbidden();
        $this->actingAs($this->admin)->post($url, [])->assertSessionHasErrors(['category_id', 'question_en', 'question_ar', 'answer_en', 'answer_ar']);
        $payload = $this->question($category, ['followup_ids' => [$followup->id], 'source_url' => 'https://example.test/source']);
        $this->actingAs($this->admin)->post($url, $payload)->assertRedirect();
        $created = PredefinedQuestion::query()->where('source_url', 'https://example.test/source')->firstOrFail();
        $this->assertSame([$followup->id], $created->followups()->pluck('ai_chatbot_predefined_questions.id')->all());
        $this->actingAs($this->admin)->delete("{$url}/{$created->id}", ['confirm' => '1'])->assertRedirect();
        $this->assertModelMissing($created);
    }

    public function test_missing_and_invalid_public_requests_have_safe_errors(): void
    {
        $this->getJson('/api/v1/chatbot/questions/999999')->assertNotFound();
        $this->getJson('/api/v1/chatbot/categories?locale=fr')->assertUnprocessable();
    }

    public function test_predefined_question_uses_approved_content_without_calling_ai(): void
    {
        Http::preventStrayRequests();
        $category = QuestionCategory::query()->create($this->category());
        $followup = PredefinedQuestion::query()->create($this->question($category, ['question_en' => 'Next question']));
        $question = PredefinedQuestion::query()->create($this->question($category, [
            'question_en' => 'Approved question',
            'answer_en' => 'Approved answer from CMS',
            'source_url' => 'https://example.test/approved',
        ]));
        $question->followups()->attach($followup, ['sort_order' => 1]);

        $created = $this->postJson('/api/ai-chatbot/conversations')->assertCreated();
        $payload = [
            'conversation_id' => $created->json('data.id'),
            'idempotency_key' => 'predefined-1',
            'message' => 'Spoofed frontend text',
            'predefined_question_id' => $question->id,
            'locale' => 'en',
        ];
        $response = $this->withHeader('X-AI-Chatbot-Token', $created->json('visitor_token'))
            ->postJson('/api/ai-chatbot/messages', $payload)
            ->assertCreated()
            ->assertJsonPath('data.content', 'Approved answer from CMS')
            ->assertJsonPath('data.predefined_question_id', $question->id)
            ->assertJsonPath('data.sources.0.url', 'https://example.test/approved')
            ->assertJsonPath('data.followups.0.id', $followup->id);

        $this->assertDatabaseHas(config('ai-chatbot.tables.messages'), ['role' => 'user', 'content' => 'Approved question']);
        $this->assertDatabaseMissing(config('ai-chatbot.tables.messages'), ['content' => 'Spoofed frontend text']);
        $this->withHeader('X-AI-Chatbot-Token', $created->json('visitor_token'))
            ->postJson('/api/ai-chatbot/messages', $payload)
            ->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame(2, \Pgs\LaravelAiChatbot\Models\Message::query()->count());
        Http::assertNothingSent();
    }

    public function test_inactive_or_disabled_predefined_questions_are_not_accepted(): void
    {
        $category = QuestionCategory::query()->create($this->category());
        $question = PredefinedQuestion::query()->create($this->question($category, ['is_active' => false]));
        $created = $this->postJson('/api/ai-chatbot/conversations')->assertCreated();
        $payload = ['conversation_id' => $created->json('data.id'), 'idempotency_key' => 'inactive-1', 'message' => 'Question', 'predefined_question_id' => $question->id];

        $this->withHeader('X-AI-Chatbot-Token', $created->json('visitor_token'))
            ->postJson('/api/ai-chatbot/messages', $payload)
            ->assertNotFound()->assertJsonPath('error.code', 'predefined_question_not_found');

        $question->update(['is_active' => true]);
        config()->set('ai-chatbot.widget.predefined_questions_enabled', false);
        $payload['idempotency_key'] = 'disabled-1';
        $this->withHeader('X-AI-Chatbot-Token', $created->json('visitor_token'))
            ->postJson('/api/ai-chatbot/messages', $payload)
            ->assertNotFound()->assertJsonPath('error.code', 'predefined_questions_disabled');
    }

    private function category(array $overrides = []): array
    {
        return array_merge(['slug' => 'category', 'title_en' => 'Title', 'title_ar' => 'عنوان', 'description_en' => null, 'description_ar' => null, 'sort_order' => 1, 'is_active' => true], $overrides);
    }

    private function question(QuestionCategory $category, array $overrides = []): array
    {
        return array_merge(['category_id' => $category->id, 'question_en' => 'Question', 'question_ar' => 'سؤال', 'answer_en' => 'Approved answer', 'answer_ar' => 'إجابة معتمدة', 'source_url' => null, 'sort_order' => 1, 'is_active' => true], $overrides);
    }
}
