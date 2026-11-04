<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tests\Feature\Knowledge;

use Illuminate\Support\Facades\Queue;
use Pgs\LaravelAiChatbot\Jobs\SyncKnowledgeSourceJob;
use Pgs\LaravelAiChatbot\Knowledge\KnowledgeSourceRegistry;
use Pgs\LaravelAiChatbot\Tests\Fixtures\ArrayKnowledgeSource;
use Pgs\LaravelAiChatbot\Tests\TestCase;

final class SyncKnowledgeCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(KnowledgeSourceRegistry::class)->register('pages', new ArrayKnowledgeSource);
    }

    public function test_command_supports_source_and_locale_filters(): void
    {
        $this->artisan('ai-chatbot:sync', ['--source' => 'pages', '--locale' => 'ar'])
            ->assertSuccessful();

        $this->assertDatabaseCount(config('ai-chatbot.tables.knowledge_chunks'), 1);
        $this->assertDatabaseHas(config('ai-chatbot.tables.knowledge_chunks'), ['locale' => 'ar']);
    }

    public function test_fresh_sync_requires_confirmation(): void
    {
        $this->artisan('ai-chatbot:sync', ['--source' => 'pages', '--fresh' => true])
            ->expectsConfirmation(
                'This will delete the selected knowledge index scope before rebuilding it. Continue?',
                'no',
            )
            ->assertFailed();
    }

    public function test_sync_can_be_dispatched_to_the_background_queue(): void
    {
        Queue::fake();

        $this->artisan('ai-chatbot:sync', ['--source' => 'pages', '--locale' => 'en', '--queue' => true])
            ->assertSuccessful();

        Queue::assertPushed(SyncKnowledgeSourceJob::class, fn (SyncKnowledgeSourceJob $job): bool => $job->source === 'pages' && $job->locale === 'en' && ! $job->fresh
        );
    }
}
