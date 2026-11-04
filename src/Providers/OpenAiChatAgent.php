<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Providers;

use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Attributes\Strict;

#[Strict]
final class OpenAiChatAgent extends AnonymousAgent
{
    public function __construct(
        string $instructions,
        iterable $messages,
        private readonly int $outputTokenLimit,
        iterable $tools = [],
        private readonly int $stepLimit = 1,
    ) {
        parent::__construct($instructions, $messages, $tools);
    }

    public function maxTokens(): int
    {
        return $this->outputTokenLimit;
    }

    public function maxSteps(): int
    {
        return $this->stepLimit;
    }
}
