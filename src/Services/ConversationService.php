<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Pgs\LaravelAiChatbot\Exceptions\ConversationAccessException;
use Pgs\LaravelAiChatbot\Models\Conversation;
use Pgs\LaravelAiChatbot\Models\Message;
use Pgs\LaravelAiChatbot\Models\PredefinedQuestion;

final class ConversationService
{
    public function __construct(private readonly ChatbotService $chatbot) {}

    /** @return array{conversation: Conversation, credential: string} */
    public function create(?Authenticatable $actor, ?string $title, string $locale, string $ip): array
    {
        $credential = $actor ? '' : Str::random(64);
        $ttl = max(1, min(43_800, (int) config('ai-chatbot.api.conversation_ttl_hours', 168)));

        $conversation = Conversation::query()->create([
            'participant_type' => $actor ? $actor::class : null,
            'participant_id' => $actor ? (string) $actor->getAuthIdentifier() : null,
            'visitor_id_hash' => $actor ? null : $this->fingerprint($credential),
            'credential_hash' => $actor ? null : Hash::make($credential),
            'title' => $title,
            'status' => 'active',
            'metadata' => ['locale' => $locale, 'creation_ip_hash' => $this->fingerprint($ip)],
            'expires_at' => now()->addHours($ttl),
        ]);

        return compact('conversation', 'credential');
    }

    public function authorize(string $id, ?Authenticatable $actor, ?string $credential): Conversation
    {
        $conversation = Conversation::query()->find($id);
        if (! $conversation) {
            throw new ConversationAccessException('conversation_not_found', 404);
        }
        if ($conversation->expires_at?->isPast() || $conversation->status !== 'active') {
            throw new ConversationAccessException('conversation_expired', 410);
        }

        $ownedByActor = $actor
            && $conversation->participant_type === $actor::class
            && hash_equals((string) $conversation->participant_id, (string) $actor->getAuthIdentifier());
        $ownedByCredential = $conversation->participant_type === null && is_string($credential) && $credential !== ''
            && is_string($conversation->credential_hash)
            && Hash::check($credential, $conversation->credential_hash);

        if (! $ownedByActor && ! $ownedByCredential) {
            throw new ConversationAccessException('conversation_not_found', 404);
        }

        return $conversation;
    }

    /** @return Collection<int, Message> */
    public function messages(Conversation $conversation, int $limit, ?string $before): Collection
    {
        $limit = max(1, min((int) config('ai-chatbot.api.max_history_messages', 50), $limit));

        return $conversation->messages()->select(['id', 'conversation_id', 'role', 'content', 'status', 'metadata', 'created_at'])
            ->when($before, fn ($query) => $query->where('created_at', '<', $before))
            ->latest('created_at')->limit($limit)->get()->reverse()->values();
    }

    /** @return array{user: Message, assistant: Message, duplicate: bool} */
    public function send(Conversation $conversation, array $data, ?Authenticatable $actor): array
    {
        $existing = $conversation->messages()->where('client_request_id', $data['idempotency_key'])->first();
        if ($existing) {
            return $this->existingResponse($conversation, $existing);
        }

        $this->enforceDailyBudget($conversation);
        $locale = ($data['locale'] ?? 'en') === 'ar' ? 'ar' : 'en';
        $predefined = $this->predefinedQuestion($data);
        $message = $predefined ? (string) $predefined->{"question_{$locale}"} : trim($data['message']);

        try {
            $user = Message::query()->create([
                'conversation_id' => $conversation->id,
                'client_request_id' => $data['idempotency_key'],
                'role' => 'user', 'content' => $message, 'status' => 'completed',
                'metadata' => ['locale' => $locale, 'predefined_question_id' => $predefined?->getKey()],
            ]);
        } catch (QueryException $exception) {
            $existing = $conversation->messages()->where('client_request_id', $data['idempotency_key'])->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->existingResponse($conversation, $existing);
        }

        if ($predefined) {
            return $this->sendPredefined($conversation, $user, $predefined, $locale);
        }

        $history = $this->messages($conversation, (int) config('ai-chatbot.chat.max_conversation_messages', 10), null)
            ->map(fn (Message $message): array => ['role' => $message->role, 'content' => (string) $message->content])->all();
        $startedAt = hrtime(true);
        $response = $this->chatbot->ask($message, $locale, $history, $data['sources'] ?? [], $actor);
        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        $assistant = DB::transaction(function () use ($conversation, $user, $response, $latencyMs): Message {
            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'response_to_message_id' => $user->id,
                'role' => 'assistant', 'content' => $response->answer,
                'status' => $response->errorCode === 'provider_unavailable' ? 'failed' : 'completed',
                'input_tokens' => $response->usage->inputTokens,
                'output_tokens' => $response->usage->outputTokens,
                'latency_ms' => $latencyMs,
                'estimated_cost_usd' => $response->usage->estimatedCostUsd,
                'error_code' => $response->errorCode,
                'metadata' => ['locale' => $response->locale, 'sources' => array_map(fn ($source) => $source->toArray(), $response->sources), 'fallback' => $response->fallback, 'error_code' => $response->errorCode],
            ]);
            $conversation->update(['last_message_at' => now()]);

            return $message;
        });

        return ['user' => $user, 'assistant' => $assistant, 'duplicate' => false];
    }

    /** @return array{user: Message, assistant: Message, duplicate: false} */
    private function sendPredefined(Conversation $conversation, Message $user, PredefinedQuestion $question, string $locale): array
    {
        $sources = $question->source_url ? [[
            'id' => 'P'.$question->getKey(),
            'source' => 'predefined_question',
            'source_id' => (string) $question->getKey(),
            'title' => (string) $question->{"question_{$locale}"},
            'url' => (string) $question->source_url,
        ]] : [];
        $followups = $question->followups->map(fn (PredefinedQuestion $followup): array => [
            'id' => $followup->getKey(),
            'question' => (string) $followup->{"question_{$locale}"},
        ])->unique('id')->values()->all();

        $assistant = DB::transaction(function () use ($conversation, $user, $question, $locale, $sources, $followups): Message {
            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'response_to_message_id' => $user->id,
                'role' => 'assistant',
                'content' => (string) $question->{"answer_{$locale}"},
                'status' => 'completed',
                'metadata' => [
                    'locale' => $locale,
                    'sources' => $sources,
                    'followups' => $followups,
                    'predefined_question_id' => $question->getKey(),
                    'fallback' => false,
                ],
            ]);
            $conversation->update(['last_message_at' => now()]);

            return $message;
        });

        return ['user' => $user, 'assistant' => $assistant, 'duplicate' => false];
    }

    public function delete(Conversation $conversation): void
    {
        $conversation->update(['status' => 'deleted']);
        $conversation->delete();
    }

    private function existingResponse(Conversation $conversation, Message $user): array
    {
        $assistant = $conversation->messages()->where('response_to_message_id', $user->id)->first();
        if (! $assistant) {
            throw new ConversationAccessException('message_processing', 409);
        }

        return ['user' => $user, 'assistant' => $assistant, 'duplicate' => true];
    }

    private function enforceDailyBudget(Conversation $conversation): void
    {
        $query = $conversation->messages()->where('role', 'user')->where('created_at', '>=', now()->startOfDay());
        if ($query->count() >= max(1, (int) config('ai-chatbot.api.daily_message_budget', 50))) {
            throw new ConversationAccessException('daily_budget_exceeded', 429);
        }

        $tokens = (int) $conversation->messages()->where('role', 'assistant')->where('created_at', '>=', now()->startOfDay())
            ->selectRaw('COALESCE(SUM(input_tokens + output_tokens), 0) as aggregate')->value('aggregate');
        if ($tokens >= max(1, (int) config('ai-chatbot.api.daily_token_budget', 50_000))) {
            throw new ConversationAccessException('daily_budget_exceeded', 429);
        }
    }

    private function predefinedQuestion(array $data): ?PredefinedQuestion
    {
        if (! isset($data['predefined_question_id'])) {
            return null;
        }

        if (! config('ai-chatbot.widget.predefined_questions_enabled', true)) {
            throw new ConversationAccessException('predefined_questions_disabled', 404);
        }

        $question = PredefinedQuestion::query()
            ->published()
            ->with(['followups' => fn ($query) => $query->published()])
            ->find($data['predefined_question_id']);

        if (! $question) {
            throw new ConversationAccessException('predefined_question_not_found', 404);
        }

        return $question;
    }

    private function fingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
