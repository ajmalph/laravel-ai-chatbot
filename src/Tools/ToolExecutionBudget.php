<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tools;

final class ToolExecutionBudget
{
    private int $calls = 0;

    private int $resultCharacters = 0;

    public function __construct(
        private readonly int $maximumCalls,
        private readonly int $maximumResultCharacters,
    ) {}

    public function claimCall(): bool
    {
        $this->calls++;

        return $this->calls <= $this->maximumCalls;
    }

    public function remainingResultCharacters(): int
    {
        return max(0, $this->maximumResultCharacters - $this->resultCharacters);
    }

    public function recordResult(string $result): void
    {
        $this->resultCharacters += mb_strlen($result);
    }
}
