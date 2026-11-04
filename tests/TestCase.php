<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tests;

use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase as Orchestra;
use Pgs\LaravelAiChatbot\AiChatbotServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function getPackageProviders($app): array
    {
        return [AiChatbotServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('ai-chatbot.knowledge_sources.register_bundled', false);
        $app['config']->set('ai-chatbot.tools.register_bundled', false);
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
