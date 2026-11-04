<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Data;

final readonly class ProviderResponse
{
    public function __construct(
        public string $text,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public ?string $model = null,
        public ?string $requestId = null,
    ) {}
}
