<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Contracts;

interface ToolInterface
{
    public function name(): string;

    public function description(): string;

    /** @return array<string, mixed> */
    public function argumentsSchema(): array;

    /** @return array{public?: bool, abilities?: list<string>} */
    public function authorizationRequirements(): array;

    /** @param array<string, mixed> $arguments */
    public function executeReadOnly(array $arguments): mixed;

    public function maximumResultCharacters(): int;
}
