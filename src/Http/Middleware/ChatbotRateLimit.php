<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ChatbotRateLimit
{
    public function __construct(private readonly RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $maximum = max(1, (int) config('ai-chatbot.api.rate_limit_per_minute', 20));
        $keys = ['ip:'.hash('sha256', (string) $request->ip())];
        if ($request->user()) {
            $keys[] = 'user:'.$request->user()->getAuthIdentifier();
        } elseif ($token = $request->header('X-AI-Chatbot-Token')) {
            $keys[] = 'visitor:'.hash_hmac('sha256', $token, (string) config('app.key'));
        }

        foreach ($keys as $key) {
            $key = 'ai-chatbot:'.$key;
            if ($this->limiter->tooManyAttempts($key, $maximum)) {
                return response()->json(['error' => ['code' => 'rate_limit_exceeded']], 429, ['Retry-After' => (string) $this->limiter->availableIn($key)]);
            }
        }
        foreach ($keys as $key) {
            $this->limiter->hit('ai-chatbot:'.$key, 60);
        }

        return $next($request);
    }
}
