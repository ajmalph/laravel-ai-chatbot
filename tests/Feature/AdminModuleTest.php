<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Gate;
use Pgs\LaravelAiChatbot\Facades\AiChatbot;
use Pgs\LaravelAiChatbot\Models\Conversation;
use Pgs\LaravelAiChatbot\Models\KnowledgeChunk;
use Pgs\LaravelAiChatbot\Models\Message;
use Pgs\LaravelAiChatbot\Tests\Fixtures\ArrayKnowledgeSource;
use Pgs\LaravelAiChatbot\Tests\TestCase;

final class AdminModuleTest extends TestCase
{
    private GenericUser $admin;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('ai-chatbot.admin.enabled', true);
        $app['config']->set('ai-chatbot.admin.authentication_middleware', ['web']);
        $app['config']->set('ai-chatbot.admin.authorization_middleware', [SubstituteBindings::class]);
        $app['config']->set('ai-chatbot.admin.retention_days', 0);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = new GenericUser(['id' => 1, 'name' => 'Admin']);
        Gate::define('ai-chatbot.admin.view', fn ($user): bool => (int) $user->getAuthIdentifier() === 1);
        Gate::define('ai-chatbot.admin.settings', fn ($user): bool => (int) $user->getAuthIdentifier() === 1);
        Gate::define('ai-chatbot.admin.sync', fn ($user): bool => (int) $user->getAuthIdentifier() === 1);
        Gate::define('ai-chatbot.admin.delete', fn ($user): bool => (int) $user->getAuthIdentifier() === 1);
        AiChatbot::registerKnowledgeSource('fixture', ArrayKnowledgeSource::class);
    }

    public function test_every_admin_area_denies_unauthorized_access(): void
    {
        $this->get('/admin/ai-chatbot')->assertForbidden();
        $this->get('/admin/ai-chatbot/conversations')->assertForbidden();
        $this->get('/admin/ai-chatbot/knowledge')->assertForbidden();
        $this->get('/admin/ai-chatbot/settings')->assertForbidden();
        $this->post('/admin/ai-chatbot/knowledge/sync', ['source' => 'fixture'])->assertForbidden();
    }

    public function test_dashboard_aggregates_metrics_without_exposing_secrets(): void
    {
        $conversation = Conversation::query()->create(['status' => 'active', 'expires_at' => now()->addDay()]);
        Message::query()->create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'private response', 'input_tokens' => 20, 'output_tokens' => 10, 'latency_ms' => 150, 'estimated_cost_usd' => 0.0025]);
        Message::query()->create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'failed private response', 'input_tokens' => 5, 'output_tokens' => 2, 'latency_ms' => 250, 'error_code' => 'provider_error']);
        config()->set('ai-chatbot.providers.openai.api_key', 'never-render-this-key');

        $response = $this->actingAs($this->admin)->get('/admin/ai-chatbot')->assertOk();
        $response->assertSee('Conversations')->assertSee('API failures')->assertSee('200');
        $response->assertDontSee('private response')->assertDontSee('never-render-this-key');
    }

    public function test_settings_are_allow_listed_validated_and_never_include_api_keys(): void
    {
        $this->actingAs($this->admin)->putJson('/admin/ai-chatbot/settings', [])->assertUnprocessable();
        $payload = ['enabled' => true, 'screen_title_en' => 'Assistant', 'screen_title_ar' => 'المساعد', 'welcome_en' => 'Welcome', 'welcome_ar' => 'مرحبا', 'fallback_en' => 'Fallback', 'fallback_ar' => 'تعذر', 'default_followups_en' => [], 'default_followups_ar' => [], 'predefined_questions_enabled' => true, 'retrieval_limit' => 4, 'retention_days' => 45, 'daily_message_budget' => 30, 'daily_token_budget' => 30000];
        $this->actingAs($this->admin)->put('/admin/ai-chatbot/settings', $payload)->assertRedirect();
        $this->assertDatabaseHas(config('ai-chatbot.tables.settings'), ['key' => 'retention_days']);
        $this->actingAs($this->admin)->get('/admin/ai-chatbot/settings')->assertOk()->assertDontSee('OPENAI_API_KEY');
    }

    public function test_knowledge_management_validates_sources_and_synchronizes_registered_source(): void
    {
        $this->actingAs($this->admin)->postJson('/admin/ai-chatbot/knowledge/sync', ['source' => 'unknown'])->assertUnprocessable();
        $this->actingAs($this->admin)->post('/admin/ai-chatbot/knowledge/sync', ['source' => 'fixture', 'locale' => 'en'])->assertRedirect();
        $this->assertSame(1, KnowledgeChunk::query()->where('source_type', 'fixture')->where('locale', 'en')->count());
        $this->actingAs($this->admin)->get('/admin/ai-chatbot/knowledge?source=fixture')->assertOk()->assertSee('English title');
    }

    public function test_authorized_retention_delete_soft_deletes_conversation(): void
    {
        $conversation = Conversation::query()->create(['status' => 'active', 'expires_at' => now()->addDay()]);
        $this->actingAs($this->admin)->delete('/admin/ai-chatbot/conversations/'.$conversation->id)->assertRedirect();
        $this->assertSoftDeleted(config('ai-chatbot.tables.conversations'), ['id' => $conversation->id]);
    }
}
