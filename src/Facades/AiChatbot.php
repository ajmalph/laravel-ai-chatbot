<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Facades;

use Illuminate\Support\Facades\Facade;
use Pgs\LaravelAiChatbot\AiChatbotManager;

/**
 * @method static mixed config(?string $key = null, mixed $default = null)
 * @method static \Pgs\LaravelAiChatbot\AiChatbotManager registerKnowledgeSource(string $name, string|\Pgs\LaravelAiChatbot\Contracts\KnowledgeSourceInterface $source)
 * @method static \Pgs\LaravelAiChatbot\AiChatbotManager registerTool(string $name, string|\Pgs\LaravelAiChatbot\Contracts\ToolInterface $tool)
 * @method static \Pgs\LaravelAiChatbot\Contracts\KnowledgeSourceInterface knowledgeSource(string $name)
 * @method static \Pgs\LaravelAiChatbot\Knowledge\KnowledgeSourceRegistry knowledgeSources()
 * @method static array retrieve(string $query, string $locale = 'en', array $sources = [], int $limit = 5)
 * @method static \Pgs\LaravelAiChatbot\Data\ChatbotResponse ask(string $question, string $locale = 'en', array $conversation = [], array $sources = [], mixed $actor = null)
 *
 * @see AiChatbotManager
 */
final class AiChatbot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ai-chatbot';
    }
}
