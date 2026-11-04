<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Data;

final readonly class Usage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $totalTokens = 0,
        public ?float $estimatedCostUsd = null,
    ) {}

    /** @return array{input_tokens: int, output_tokens: int, total_tokens: int, estimated_cost_usd: float|null} */
    public function toArray(): array
    {
        return [
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens' => $this->totalTokens,
            'estimated_cost_usd' => $this->estimatedCostUsd,
        ];
    }
}
