<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Routing;

use Illuminate\Contracts\Routing\Registrar;
use RuntimeException;

final class AdminRouteRegistrar
{
    public function __construct(private readonly Registrar $router) {}

    public function register(): void
    {
        $authentication = array_values((array) config('ai-chatbot.admin.authentication_middleware', []));
        $authorization = array_values((array) config('ai-chatbot.admin.authorization_middleware', []));
        if ($authentication === []) {
            throw new RuntimeException('AI chatbot admin routes require explicit authentication middleware.');
        }

        $this->router->group([
            'as' => 'ai-chatbot.admin.',
            'middleware' => array_values(array_unique([...$authentication, ...$authorization])),
            'prefix' => config('ai-chatbot.admin.prefix', 'admin/ai-chatbot'),
        ], __DIR__.'/../../routes/admin.php');
    }
}
