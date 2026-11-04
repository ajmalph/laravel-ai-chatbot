<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Admin;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

final class AdminAuthorizationService
{
    public function authorize(?Authenticatable $user, string $ability): void
    {
        abort_unless($user, 403);

        if (config('ai-chatbot.admin.authorization_middleware', []) !== []) {
            abort_unless(Gate::forUser($user)->allows($ability), 403);
        }
    }
}
