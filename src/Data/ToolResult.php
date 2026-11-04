<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Data;

final readonly class ToolResult
{
    public function __construct(
        public string $callId,
        public string $tool,
        public string $status,
        public string $output,
        public ?string $errorCode,
        public int $durationMilliseconds,
    ) {}

    public function successful(): bool
    {
        return $this->status === 'completed';
    }
}
