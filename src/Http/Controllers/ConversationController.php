<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pgs\LaravelAiChatbot\Http\Requests\CreateConversationRequest;
use Pgs\LaravelAiChatbot\Http\Requests\ListMessagesRequest;
use Pgs\LaravelAiChatbot\Http\Resources\ConversationResource;
use Pgs\LaravelAiChatbot\Http\Resources\MessageResource;
use Pgs\LaravelAiChatbot\Models\Conversation;
use Pgs\LaravelAiChatbot\Services\ConversationService;

final class ConversationController
{
    public function __construct(private readonly ConversationService $conversations) {}

    public function store(CreateConversationRequest $request): JsonResponse
    {
        if (! config('ai-chatbot.api.authentication_optional', true) && ! $request->user()) {
            return response()->json(['error' => ['code' => 'authentication_required']], 401);
        }

        $data = $request->validated();
        $created = $this->conversations->create($request->user(), $data['title'] ?? null, $data['locale'] ?? 'en', (string) $request->ip());

        return response()->json(['data' => (new ConversationResource($created['conversation']))->resolve($request), 'visitor_token' => $created['credential'] !== '' ? $created['credential'] : null], 201);
    }

    public function messages(ListMessagesRequest $request, string $id): JsonResponse
    {
        $conversation = $request->attributes->get('ai_chatbot_conversation');
        $data = $request->validated();
        $messages = $this->conversations->messages($conversation, (int) ($data['limit'] ?? 50), $data['before'] ?? null);

        return response()->json(['data' => MessageResource::collection($messages)->resolve($request)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var Conversation $conversation */
        $conversation = $request->attributes->get('ai_chatbot_conversation');
        $this->conversations->delete($conversation);

        return response()->json(null, 204);
    }
}
