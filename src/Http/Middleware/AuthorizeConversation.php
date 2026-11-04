<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pgs\LaravelAiChatbot\Exceptions\ConversationAccessException;
use Pgs\LaravelAiChatbot\Services\ConversationService;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeConversation
{
    public function __construct(private readonly ConversationService $conversations) {}

    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) ($request->route('id') ?? $request->input('conversation_id', ''));
        try {
            $conversation = $this->conversations->authorize($id, $request->user(), $request->header('X-AI-Chatbot-Token'));
        } catch (ConversationAccessException $exception) {
            return response()->json(['error' => ['code' => $exception->errorCode]], $exception->status);
        }
        $request->attributes->set('ai_chatbot_conversation', $conversation);

        return $next($request);
    }
}
