<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Pgs\LaravelAiChatbot\AiChatbotManager;
use Pgs\LaravelAiChatbot\AiChatbotServiceProvider;
use Pgs\LaravelAiChatbot\Facades\AiChatbot;
use Pgs\LaravelAiChatbot\Tests\TestCase;

final class PackageInstallationTest extends TestCase
{
    public function test_package_is_discovered_and_bindings_resolve(): void
    {
        $this->assertInstanceOf(AiChatbotManager::class, app('ai-chatbot'));
        $this->assertSame('openai', AiChatbot::config('provider'));
    }

    public function test_package_migrations_run_on_an_isolated_database(): void
    {
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));

        foreach (config('ai-chatbot.tables') as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }
    }

    public function test_publishable_resources_are_registered(): void
    {
        $configPaths = ServiceProvider::pathsToPublish(
            AiChatbotServiceProvider::class,
            'ai-chatbot-config',
        );
        $migrationPaths = ServiceProvider::pathsToPublish(
            AiChatbotServiceProvider::class,
            'ai-chatbot-migrations',
        );
        $viewPaths = ServiceProvider::pathsToPublish(AiChatbotServiceProvider::class, 'ai-chatbot-views');
        $assetPaths = ServiceProvider::pathsToPublish(AiChatbotServiceProvider::class, 'ai-chatbot-assets');

        $this->assertNotEmpty($configPaths);
        $this->assertNotEmpty($migrationPaths);
        $this->assertNotEmpty($viewPaths);
        $this->assertNotEmpty($assetPaths);
    }

    public function test_admin_routes_are_disabled_by_default(): void
    {
        $this->get('/admin/ai-chatbot')->assertNotFound();
    }
}
