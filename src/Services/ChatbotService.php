<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Services;

use Pgs\LaravelAiChatbot\Contracts\AiProviderInterface;
use Pgs\LaravelAiChatbot\Contracts\KnowledgeRetrieverInterface;
use Pgs\LaravelAiChatbot\Data\ChatbotResponse;
use Pgs\LaravelAiChatbot\Data\SourceReference;
use Pgs\LaravelAiChatbot\Data\Usage;
use Pgs\LaravelAiChatbot\Exceptions\AiProviderException;
use Pgs\LaravelAiChatbot\Retrieval\RetrievalResult;
use Pgs\LaravelAiChatbot\Tools\ToolRegistry;

final class ChatbotService
{
    public function __construct(
        private readonly KnowledgeRetrieverInterface $retriever,
        private readonly AiProviderInterface $provider,
        private readonly PromptBuilder $prompts,
        private readonly UsageTrackingService $usage,
        private readonly ToolRegistry $tools,
    ) {}

    /**
     * @param  list<array{role?: mixed, content?: mixed}>  $conversation
     * @param  list<string>  $sources
     */
    public function ask(
        string $question,
        string $locale = 'en',
        array $conversation = [],
        array $sources = [],
        mixed $actor = null,
    ): ChatbotResponse {
        $locale = $locale === 'ar' ? 'ar' : 'en';
        $questionLimit = max(1, min(10_000, (int) config('ai-chatbot.chat.max_question_characters', 1000)));
        $question = trim(mb_substr($question, 0, $questionLimit));
        if ($question === '') {
            return $this->fallback($locale, 'invalid_question');
        }

        $results = $this->retriever->retrieve(
            $question,
            $locale,
            $sources,
            max(1, min(20, (int) config('ai-chatbot.chat.retrieval_limit', 5))),
        );

        $tools = array_values($this->tools->all());

        if ($results === [] && $tools === []) {
            return $this->fallback($locale, 'insufficient_knowledge');
        }

        $prompt = $this->prompts->build($question, $locale, $results);
        if ($prompt['results'] === [] && $tools === []) {
            return $this->fallback($locale, 'insufficient_knowledge');
        }

        try {
            $providerResponse = $this->provider->generate(
                $prompt['instructions'],
                $prompt['prompt'],
                $this->boundedConversation($conversation),
                $tools,
                $actor,
            );
        } catch (AiProviderException $exception) {
            return $this->fallback($locale, $exception->errorCode);
        }

        return new ChatbotResponse(
            answer: $providerResponse->text,
            locale: $locale,
            sources: $this->references($prompt['results']),
            usage: $this->usage->fromProvider($providerResponse),
            grounded: true,
        );
    }

    /** @param list<array{role?: mixed, content?: mixed}> $conversation
     * @return list<array{role: 'user'|'assistant', content: string}>
     */
    private function boundedConversation(array $conversation): array
    {
        $maximumMessages = max(0, (int) config('ai-chatbot.chat.max_conversation_messages', 10));
        $remainingCharacters = max(0, min(50_000, (int) config('ai-chatbot.chat.max_conversation_characters', 6000)));
        $bounded = [];

        if ($maximumMessages === 0 || $remainingCharacters === 0) {
            return [];
        }

        foreach (array_reverse(array_slice($conversation, -min(50, $maximumMessages))) as $message) {
            $role = $message['role'] ?? null;
            if (! in_array($role, ['user', 'assistant'], true) || ! is_string($message['content'] ?? null)) {
                continue;
            }

            $content = trim(mb_substr($message['content'], 0, $remainingCharacters));
            if ($content === '') {
                continue;
            }

            array_unshift($bounded, ['role' => $role, 'content' => $content]);
            $remainingCharacters -= mb_strlen($content);
            if ($remainingCharacters <= 0) {
                break;
            }
        }

        return $bounded;
    }

    /** @param list<RetrievalResult> $results
     * @return list<SourceReference>
     */
    private function references(array $results): array
    {
        $references = [];
        $seen = [];

        foreach ($results as $result) {
            $key = $result->source.'|'.$result->sourceId.'|'.$result->url;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $references[] = new SourceReference(
                id: 'S'.(count($references) + 1),
                source: $result->source,
                sourceId: $result->sourceId,
                title: $result->title,
                url: $result->url,
            );
        }

        return $references;
    }

    private function fallback(string $locale, string $errorCode): ChatbotResponse
    {
        return new ChatbotResponse(
            answer: (string) config("ai-chatbot.chat.fallback.{$locale}"),
            locale: $locale,
            sources: [],
            usage: new Usage,
            grounded: false,
            fallback: true,
            errorCode: $errorCode,
        );
    }
}
