<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tools;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Pgs\LaravelAiChatbot\Contracts\ToolInterface;
use RuntimeException;

final class ToolRegistry
{
    /** @var array<string, class-string<ToolInterface>|ToolInterface> */
    private array $tools = [];

    public function __construct(
        private readonly Container $container,
        private readonly ToolSchemaValidator $schemas,
    ) {}

    /** @param class-string<ToolInterface>|ToolInterface $tool */
    public function register(string $name, string|ToolInterface $tool): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name) !== 1) {
            throw new InvalidArgumentException("Invalid tool name [{$name}].");
        }

        if (isset($this->tools[$name])) {
            throw new InvalidArgumentException("Tool [{$name}] is already registered.");
        }

        if (is_string($tool) && ! is_a($tool, ToolInterface::class, true)) {
            throw new InvalidArgumentException("Tool [{$tool}] must implement ".ToolInterface::class.'.');
        }

        $resolved = is_string($tool) ? $this->container->make($tool) : $tool;
        if (! $resolved instanceof ToolInterface || $resolved->name() !== $name) {
            throw new InvalidArgumentException("Registered tool [{$name}] must declare the same name.");
        }

        $this->schemas->validateDefinition($resolved->argumentsSchema());
        $this->validateAuthorization($resolved->authorizationRequirements());

        if ($resolved->maximumResultCharacters() < 1) {
            throw new InvalidArgumentException("Tool [{$name}] must declare a positive result limit.");
        }

        $this->tools[$name] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): ToolInterface
    {
        $tool = $this->tools[$name] ?? throw new InvalidArgumentException("Tool [{$name}] is not registered.");
        $resolved = is_string($tool) ? $this->container->make($tool) : $tool;

        if (! $resolved instanceof ToolInterface) {
            throw new RuntimeException("Resolved tool [{$name}] is invalid.");
        }

        return $resolved;
    }

    /** @return array<string, ToolInterface> */
    public function all(): array
    {
        $maximum = max(0, min(20, (int) config('ai-chatbot.tools.max_registered_per_request', 10)));
        $names = array_slice(array_keys($this->tools), 0, $maximum);

        return array_combine($names, array_map($this->get(...), $names));
    }

    /** @param array<string, mixed> $requirements */
    private function validateAuthorization(array $requirements): void
    {
        $public = ($requirements['public'] ?? false) === true;
        $abilities = $requirements['abilities'] ?? [];

        if (! is_array($abilities) || array_filter($abilities, fn (mixed $ability): bool => ! is_string($ability) || $ability === '') !== []) {
            throw new InvalidArgumentException('Tool authorization abilities must be non-empty strings.');
        }

        if (! $public && $abilities === []) {
            throw new InvalidArgumentException('A tool must explicitly be public or declare authorization abilities.');
        }

        if ($public && $abilities !== []) {
            throw new InvalidArgumentException('A public tool cannot also declare authorization abilities.');
        }
    }
}
