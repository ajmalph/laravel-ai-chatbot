<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot;

use Illuminate\Contracts\Config\Repository;
use Pgs\LaravelAiChatbot\Contracts\KnowledgeRetrieverInterface;
use Pgs\LaravelAiChatbot\Contracts\KnowledgeSourceInterface;
use Pgs\LaravelAiChatbot\Contracts\ToolInterface;
use Pgs\LaravelAiChatbot\Data\ChatbotResponse;
use Pgs\LaravelAiChatbot\Knowledge\KnowledgeSourceRegistry;
use Pgs\LaravelAiChatbot\Retrieval\RetrievalResult;
use Pgs\LaravelAiChatbot\Services\ChatbotService;
use Pgs\LaravelAiChatbot\Tools\ToolRegistry;

final class AiChatbotManager
{
    public function __construct(
        private readonly Repository $config,
        private readonly KnowledgeSourceRegistry $knowledgeSources,
        private readonly KnowledgeRetrieverInterface $retriever,
        private readonly ChatbotService $chatbot,
        private readonly ToolRegistry $tools,
    ) {}

    public function config(?string $key = null, mixed $default = null): mixed
    {
        $key = $key === null ? 'ai-chatbot' : "ai-chatbot.{$key}";

        return $this->config->get($key, $default);
    }

    /** @param class-string<KnowledgeSourceInterface>|KnowledgeSourceInterface $source */
    public function registerKnowledgeSource(string $name, string|KnowledgeSourceInterface $source): self
    {
        $this->knowledgeSources->register($name, $source);

        return $this;
    }

    public function knowledgeSource(string $name): KnowledgeSourceInterface
    {
        return $this->knowledgeSources->get($name);
    }

    public function knowledgeSources(): KnowledgeSourceRegistry
    {
        return $this->knowledgeSources;
    }

    /** @param class-string<ToolInterface>|ToolInterface $tool */
    public function registerTool(string $name, string|ToolInterface $tool): self
    {
        $this->tools->register($name, $tool);

        return $this;
    }

    public function tools(): ToolRegistry
    {
        return $this->tools;
    }

    /** @param list<string> $sources
     * @return list<RetrievalResult>
     */
    public function retrieve(string $query, string $locale = 'en', array $sources = [], int $limit = 5): array
    {
        return $this->retriever->retrieve($query, $locale, $sources, $limit);
    }

    /** @param list<array{role?: mixed, content?: mixed}> $conversation
     * @param  list<string>  $sources
     */
    public function ask(string $question, string $locale = 'en', array $conversation = [], array $sources = [], mixed $actor = null): ChatbotResponse
    {
        return $this->chatbot->ask($question, $locale, $conversation, $sources, $actor);
    }
}
