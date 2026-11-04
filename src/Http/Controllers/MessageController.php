<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Pgs\LaravelAiChatbot\Exceptions\ConversationAccessException;
use Pgs\LaravelAiChatbot\Http\Requests\SendMessageRequest;
use Pgs\LaravelAiChatbot\Http\Resources\MessageResource;
use Pgs\LaravelAiChatbot\Services\ConversationService;

final class MessageController
{
    public function __construct(private readonly ConversationService $conversations) {}

    public function store(SendMessageRequest $request): JsonResponse
    {
        try {
            $result = $this->conversations->send($request->attributes->get('ai_chatbot_conversation'), $request->validated(), $request->user());
        } catch (ConversationAccessException $exception) {
            return response()->json(['error' => ['code' => $exception->errorCode]], $exception->status);
        }

        return response()->json([
            'data' => (new MessageResource($result['assistant']))->resolve($request),
            'duplicate' => $result['duplicate'],
        ], $result['duplicate'] ? 200 : 201);
    }
}
