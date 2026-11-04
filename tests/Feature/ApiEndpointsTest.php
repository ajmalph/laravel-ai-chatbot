<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Pgs\LaravelAiChatbot\Models\Conversation;
use Pgs\LaravelAiChatbot\Models\Message;
use Pgs\LaravelAiChatbot\Tests\TestCase;

final class ApiEndpointsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('ai-chatbot.routes.enabled', true);
        $app['config']->set('ai-chatbot.api.rate_limit_per_minute', 100);
    }

    public function test_anonymous_conversation_requires_its_secret_not_just_uuid(): void
    {
        $created = $this->postJson('/api/ai-chatbot/conversations', ['locale' => 'ar'])->assertCreated();
        $id = $created->json('data.id');

        $this->getJson("/api/ai-chatbot/conversations/{$id}/messages")->assertNotFound();
        $this->withHeader('X-AI-Chatbot-Token', 'wrong')->getJson("/api/ai-chatbot/conversations/{$id}/messages")->assertNotFound();
        $this->withHeader('X-AI-Chatbot-Token', $created->json('visitor_token'))
            ->getJson("/api/ai-chatbot/conversations/{$id}/messages")->assertOk()->assertJson(['data' => []]);

        $this->assertNotSame($created->json('visitor_token'), Conversation::query()->findOrFail($id)->credential_hash);
    }

    public function test_expired_conversation_returns_gone(): void
    {
        $created = $this->postJson('/api/ai-chatbot/conversations')->assertCreated();
        Conversation::query()->findOrFail($created->json('data.id'))->update(['expires_at' => now()->subMinute()]);

        $this->withHeader('X-AI-Chatbot-Token', $created->json('visitor_token'))
            ->getJson('/api/ai-chatbot/conversations/'.$created->json('data.id').'/messages')
            ->assertStatus(410)->assertJsonPath('error.code', 'conversation_expired');
    }

    public function test_message_submission_is_idempotent_and_history_is_bounded(): void
    {
        $created = $this->postJson('/api/ai-chatbot/conversations')->assertCreated();
        $payload = ['conversation_id' => $created->json('data.id'), 'idempotency_key' => 'request-1', 'message' => 'What is published?'];
        $this->withHeader('X-AI-Chatbot-Token', $created->json('visitor_token'))->postJson('/api/ai-chatbot/messages', $payload)->assertCreated()->assertJsonPath('duplicate', false);
        $this->withHeader('X-AI-Chatbot-Token', $created->json('visitor_token'))->postJson('/api/ai-chatbot/messages', $payload)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame(2, Message::query()->count());
        $this->withHeader('X-AI-Chatbot-Token', $created->json('visitor_token'))
            ->getJson('/api/ai-chatbot/conversations/'.$created->json('data.id').'/messages?limit=1')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_validation_and_rate_limit_errors_are_json(): void
    {
        $created = $this->postJson('/api/ai-chatbot/conversations')->assertCreated();
        $this->withHeader('X-AI-Chatbot-Token', $created->json('visitor_token'))
            ->postJson('/api/ai-chatbot/messages', ['conversation_id' => $created->json('data.id'), 'message' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors(['idempotency_key', 'message']);

        config()->set('ai-chatbot.api.rate_limit_per_minute', 3);
        $this->getJson('/api/ai-chatbot/config')->assertOk();
        $this->getJson('/api/ai-chatbot/config')->assertTooManyRequests()->assertJsonPath('error.code', 'rate_limit_exceeded');
    }

    public function test_delete_requires_ownership_and_soft_deletes_conversation(): void
    {
        $created = $this->postJson('/api/ai-chatbot/conversations')->assertCreated();
        $url = '/api/ai-chatbot/conversations/'.$created->json('data.id');
        $this->deleteJson($url)->assertNotFound();
        $this->withHeader('X-AI-Chatbot-Token', $created->json('visitor_token'))->deleteJson($url)->assertNoContent();
        $this->assertSoftDeleted(config('ai-chatbot.tables.conversations'), ['id' => $created->json('data.id')]);
    }

    public function test_authenticated_conversation_is_owned_by_the_authenticated_identity(): void
    {
        $owner = new GenericUser(['id' => 10, 'name' => 'Owner']);
        $other = new GenericUser(['id' => 11, 'name' => 'Other']);
        $created = $this->actingAs($owner)->postJson('/api/ai-chatbot/conversations')->assertCreated()->assertJsonPath('visitor_token', null);
        $url = '/api/ai-chatbot/conversations/'.$created->json('data.id').'/messages';

        $this->actingAs($other)->getJson($url)->assertNotFound();
        $this->actingAs($owner)->getJson($url)->assertOk();
    }

    public function test_anonymous_access_can_be_disabled_and_daily_budget_is_enforced(): void
    {
        config()->set('ai-chatbot.api.authentication_optional', false);
        $this->postJson('/api/ai-chatbot/conversations')->assertUnauthorized()->assertJsonPath('error.code', 'authentication_required');

        config()->set('ai-chatbot.api.authentication_optional', true);
        config()->set('ai-chatbot.api.daily_message_budget', 1);
        $created = $this->postJson('/api/ai-chatbot/conversations')->assertCreated();
        $base = ['conversation_id' => $created->json('data.id'), 'message' => 'Question'];
        $this->withHeader('X-AI-Chatbot-Token', $created->json('visitor_token'))
            ->postJson('/api/ai-chatbot/messages', $base + ['idempotency_key' => 'daily-1'])->assertCreated();
        $this->withHeader('X-AI-Chatbot-Token', $created->json('visitor_token'))
            ->postJson('/api/ai-chatbot/messages', $base + ['idempotency_key' => 'daily-2'])
            ->assertTooManyRequests()->assertJsonPath('error.code', 'daily_budget_exceeded');
    }

    public function test_cleanup_soft_deletes_only_expired_conversations(): void
    {
        $expired = $this->postJson('/api/ai-chatbot/conversations')->assertCreated()->json('data.id');
        $active = $this->postJson('/api/ai-chatbot/conversations')->assertCreated()->json('data.id');
        Conversation::query()->findOrFail($expired)->update(['expires_at' => now()->subMinute()]);

        $this->artisan('ai-chatbot:cleanup --limit=1')->assertSuccessful();

        $this->assertSoftDeleted(config('ai-chatbot.tables.conversations'), ['id' => $expired]);
        $this->assertDatabaseHas(config('ai-chatbot.tables.conversations'), ['id' => $active, 'deleted_at' => null]);
    }
}
