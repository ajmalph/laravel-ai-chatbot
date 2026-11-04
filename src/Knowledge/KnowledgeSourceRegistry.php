<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Knowledge;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Pgs\LaravelAiChatbot\Contracts\KnowledgeSourceInterface;
use RuntimeException;

final class KnowledgeSourceRegistry
{
    /** @var array<string, class-string<KnowledgeSourceInterface>|KnowledgeSourceInterface> */
    private array $sources = [];

    public function __construct(private readonly Container $container) {}

    /** @param class-string<KnowledgeSourceInterface>|KnowledgeSourceInterface $source */
    public function register(string $name, string|KnowledgeSourceInterface $source): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]*$/', $name) !== 1) {
            throw new InvalidArgumentException("Invalid knowledge source name [{$name}].");
        }

        if (isset($this->sources[$name])) {
            throw new InvalidArgumentException("Knowledge source [{$name}] is already registered.");
        }

        if (is_string($source) && ! is_a($source, KnowledgeSourceInterface::class, true)) {
            throw new InvalidArgumentException("Knowledge source [{$source}] must implement ".KnowledgeSourceInterface::class.'.');
        }

        $this->sources[$name] = $source;
    }

    public function has(string $name): bool
    {
        return isset($this->sources[$name]);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->sources);
    }

    public function get(string $name): KnowledgeSourceInterface
    {
        $source = $this->sources[$name] ?? throw new InvalidArgumentException(
            "Knowledge source [{$name}] is not registered.",
        );

        $resolved = is_string($source) ? $this->container->make($source) : $source;

        if (! $resolved instanceof KnowledgeSourceInterface) {
            throw new RuntimeException("Resolved knowledge source [{$name}] is invalid.");
        }

        return $resolved;
    }

    /** @return array<string, KnowledgeSourceInterface> */
    public function all(): array
    {
        return array_combine($this->names(), array_map($this->get(...), $this->names()));
    }
}
