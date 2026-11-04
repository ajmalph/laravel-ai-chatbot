<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Data;

final readonly class ChatbotResponse
{
    /** @param list<SourceReference> $sources */
    public function __construct(
        public string $answer,
        public string $locale,
        public array $sources,
        public Usage $usage,
        public bool $grounded,
        public bool $fallback = false,
        public ?string $errorCode = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'answer' => $this->answer,
            'locale' => $this->locale,
            'sources' => array_map(fn (SourceReference $source): array => $source->toArray(), $this->sources),
            'usage' => $this->usage->toArray(),
            'grounded' => $this->grounded,
            'fallback' => $this->fallback,
            'error_code' => $this->errorCode,
        ];
    }
}
