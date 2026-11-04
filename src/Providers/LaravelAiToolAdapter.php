<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Providers;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchema as JsonSchemaFactory;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Pgs\LaravelAiChatbot\Contracts\ToolInterface;
use Pgs\LaravelAiChatbot\Tools\ToolExecutionBudget;
use Pgs\LaravelAiChatbot\Tools\ToolExecutor;

final class LaravelAiToolAdapter implements Tool
{
    public function __construct(
        private readonly ToolInterface $tool,
        private readonly ToolExecutor $executor,
        private readonly ToolExecutionBudget $budget,
        private readonly mixed $actor,
    ) {}

    public function name(): string
    {
        return $this->tool->name();
    }

    public function description(): string
    {
        return $this->tool->description();
    }

    public function handle(Request $request): string
    {
        return $this->executor->execute(
            $this->name(),
            $request->all(),
            $request->toolCallId() ?? '',
            $this->actor,
            $this->budget,
        )->output;
    }

    public function schema(JsonSchema $schema): array
    {
        $definition = $this->tool->argumentsSchema();
        $required = $definition['required'];
        $properties = [];

        foreach ($definition['properties'] as $name => $property) {
            $type = JsonSchemaFactory::fromArray($property);
            $properties[$name] = in_array($name, $required, true) ? $type->required() : $type;
        }

        return $properties;
    }
}
