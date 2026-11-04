<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Retrieval;

final readonly class RetrievalResult
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $source,
        public string $sourceId,
        public string $locale,
        public string $title,
        public string $content,
        public string $url,
        public float $score,
        public array $metadata = [],
    ) {}
}
