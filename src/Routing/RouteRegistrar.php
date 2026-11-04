<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Routing;

use Illuminate\Contracts\Routing\Registrar;

final class RouteRegistrar
{
    public function __construct(private readonly Registrar $router) {}

    public function register(): void
    {
        $this->router->group([
            'as' => 'ai-chatbot.',
            'middleware' => config('ai-chatbot.routes.middleware', ['api']),
            'prefix' => config('ai-chatbot.routes.prefix', 'api/ai-chatbot'),
        ], __DIR__.'/../../routes/api.php');
    }
}
