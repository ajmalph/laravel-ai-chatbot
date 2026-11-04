<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class ConfigController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'locales' => ['en', 'ar'],
            'default_locale' => (string) config('ai-chatbot.api.default_locale', 'en'),
            'max_message_characters' => (int) config('ai-chatbot.api.max_message_characters', 1000),
            'max_history_messages' => (int) config('ai-chatbot.api.max_history_messages', 50),
            'authentication_optional' => (bool) config('ai-chatbot.api.authentication_optional', true),
        ]]);
    }
}
