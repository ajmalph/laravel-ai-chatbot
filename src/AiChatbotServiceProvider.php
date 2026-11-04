<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiServiceProvider as LaravelAiServiceProvider;
use Pgs\LaravelAiChatbot\Admin\SettingsService;
use Pgs\LaravelAiChatbot\Console\CleanupConversationsCommand;
use Pgs\LaravelAiChatbot\Console\SyncKnowledgeCommand;
use Pgs\LaravelAiChatbot\Contracts\AiProviderInterface;
use Pgs\LaravelAiChatbot\Contracts\KnowledgeRetrieverInterface;
use Pgs\LaravelAiChatbot\Knowledge\KnowledgeSourceRegistry;
use Pgs\LaravelAiChatbot\KnowledgeSources\SearchablePostKnowledgeSource;
use Pgs\LaravelAiChatbot\Providers\OpenAiProvider;
use Pgs\LaravelAiChatbot\Retrieval\MySqlKnowledgeRetriever;
use Pgs\LaravelAiChatbot\Routing\AdminRouteRegistrar;
use Pgs\LaravelAiChatbot\Routing\RouteRegistrar;
use Pgs\LaravelAiChatbot\Services\ChatbotService;
use Pgs\LaravelAiChatbot\Tools\ToolRegistry;

final class AiChatbotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(LaravelAiServiceProvider::class);
        $this->mergeConfigFrom(__DIR__.'/../config/ai-chatbot.php', 'ai-chatbot');

        $this->app->singleton(KnowledgeSourceRegistry::class);
        $this->app->singleton(ToolRegistry::class);
        $this->app->bind(AiProviderInterface::class, function ($app): AiProviderInterface {
            $provider = (string) $app['config']->get('ai-chatbot.provider', 'openai');
            $driver = $app['config']->get("ai-chatbot.providers.{$provider}.driver", OpenAiProvider::class);
            $resolved = $app->make($driver);

            if (! $resolved instanceof AiProviderInterface) {
                throw new \RuntimeException("AI provider driver [{$driver}] must implement ".AiProviderInterface::class.'.');
            }

            return $resolved;
        });
        $this->app->bind(KnowledgeRetrieverInterface::class, function ($app): KnowledgeRetrieverInterface {
            $driver = $app['config']->get(
                'ai-chatbot.retrieval.driver',
                MySqlKnowledgeRetriever::class,
            );
            $retriever = $app->make($driver);

            if (! $retriever instanceof KnowledgeRetrieverInterface) {
                throw new \RuntimeException("Knowledge retrieval driver [{$driver}] must implement ".KnowledgeRetrieverInterface::class.'.');
            }

            return $retriever;
        });

        $this->app->singleton(AiChatbotManager::class, fn ($app): AiChatbotManager => new AiChatbotManager(
            $app->make(Repository::class),
            $app->make(KnowledgeSourceRegistry::class),
            $app->make(KnowledgeRetrieverInterface::class),
            $app->make(ChatbotService::class),
            $app->make(ToolRegistry::class),
        ));
        $this->app->alias(AiChatbotManager::class, 'ai-chatbot');
    }

    public function boot(RouteRegistrar $routes, AdminRouteRegistrar $adminRoutes, SettingsService $settings): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ai-chatbot');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'ai-chatbot');

        if ((bool) $this->app['config']->get('ai-chatbot.knowledge_sources.register_bundled', true)) {
            $manager = $this->app->make(AiChatbotManager::class);
            $manager->registerKnowledgeSource('posts', SearchablePostKnowledgeSource::class);
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $settings->apply();

        if ((bool) $this->app['config']->get('ai-chatbot.routes.enabled', false)) {
            $routes->register();
        }

        if ((bool) $this->app['config']->get('ai-chatbot.admin.enabled', false)) {
            $adminRoutes->register();
        }

        if ((bool) $this->app['config']->get('ai-chatbot.predefined.routes.enabled', true)) {
            Route::middleware((array) config('ai-chatbot.predefined.routes.middleware', ['api']))
                ->prefix((string) config('ai-chatbot.predefined.routes.prefix', 'api/v1/chatbot'))
                ->group(__DIR__.'/../routes/predefined-api.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([SyncKnowledgeCommand::class, CleanupConversationsCommand::class]);

            if ((bool) $this->app['config']->get('ai-chatbot.api.cleanup.enabled', true)) {
                $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                    $limit = max(1, min(5000, (int) config('ai-chatbot.api.cleanup.limit', 500)));
                    $schedule->command("ai-chatbot:cleanup --limit={$limit}")
                        ->dailyAt((string) config('ai-chatbot.api.cleanup.time', '03:00'))
                        ->withoutOverlapping();
                });
            }

            if ((bool) $this->app['config']->get('ai-chatbot.index.schedule.enabled', false)) {
                $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                    $schedule->command('ai-chatbot:sync --queue')
                        ->dailyAt((string) config('ai-chatbot.index.schedule.time', '02:00'))
                        ->withoutOverlapping();
                });
            }

            $this->publishes([
                __DIR__.'/../config/ai-chatbot.php' => config_path('ai-chatbot.php'),
            ], 'ai-chatbot-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'ai-chatbot-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/ai-chatbot'),
            ], 'ai-chatbot-views');

            $this->publishes([
                __DIR__.'/../resources/lang' => lang_path('vendor/ai-chatbot'),
            ], 'ai-chatbot-translations');

            $this->publishes([
                __DIR__.'/../resources/dist' => public_path('vendor/ai-chatbot'),
            ], 'ai-chatbot-assets');
        }
    }
}
