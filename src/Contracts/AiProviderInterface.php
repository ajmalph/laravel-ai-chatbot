<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Contracts;

use Pgs\LaravelAiChatbot\Data\ProviderResponse;

interface AiProviderInterface
{
    /** @param list<array{role: 'user'|'assistant', content: string}> $conversation
     * @param  list<ToolInterface>  $tools
     */
    public function generate(string $instructions, string $prompt, array $conversation = [], array $tools = [], mixed $actor = null): ProviderResponse;
}
