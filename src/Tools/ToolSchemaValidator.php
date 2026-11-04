<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tools;

use InvalidArgumentException;

final class ToolSchemaValidator
{
    /** @param array<string, mixed> $schema */
    public function validateDefinition(array $schema): void
    {
        if (($schema['type'] ?? null) !== 'object' || ! is_array($schema['properties'] ?? null)) {
            throw new InvalidArgumentException('Tool schema must be a JSON Schema object with properties.');
        }

        if (($schema['additionalProperties'] ?? null) !== false) {
            throw new InvalidArgumentException('Strict tool schemas must set additionalProperties to false.');
        }

        $required = $schema['required'] ?? null;
        if (! is_array($required)
            || array_diff(array_keys($schema['properties']), $required) !== []
            || array_diff($required, array_keys($schema['properties'])) !== []) {
            throw new InvalidArgumentException('Strict tool schemas must require every declared property.');
        }

        foreach ($schema['properties'] as $name => $property) {
            if (! is_string($name) || ! is_array($property)) {
                throw new InvalidArgumentException('Tool schema properties are malformed.');
            }
            $this->validateProperty($property);
        }
    }

    /** @param array<string, mixed> $arguments
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function validateArguments(array $arguments, array $schema): array
    {
        $this->validateDefinition($schema);
        $properties = $schema['properties'];

        if (array_diff(array_keys($arguments), array_keys($properties)) !== []) {
            throw new InvalidArgumentException('Tool arguments contain unknown properties.');
        }

        foreach ($schema['required'] as $required) {
            if (! array_key_exists($required, $arguments)) {
                throw new InvalidArgumentException('Tool arguments are missing required properties.');
            }
        }

        foreach ($arguments as $name => $value) {
            $this->validateValue($value, $properties[$name]);
        }

        return $arguments;
    }

    /** @param array<string, mixed> $property */
    private function validateProperty(array $property): void
    {
        $types = (array) ($property['type'] ?? []);
        if ($types === [] || array_diff($types, ['string', 'integer', 'number', 'boolean', 'array', 'object', 'null']) !== []) {
            throw new InvalidArgumentException('Tool schema contains an unsupported property type.');
        }

        if (in_array('object', $types, true)) {
            $this->validateDefinition($property);
        }

        if (in_array('array', $types, true) && isset($property['items'])) {
            if (! is_array($property['items'])) {
                throw new InvalidArgumentException('Tool array item schema is malformed.');
            }
            $this->validateProperty($property['items']);
        }
    }

    /** @param array<string, mixed> $property */
    private function validateValue(mixed $value, array $property): void
    {
        $types = (array) $property['type'];
        $valid = in_array($this->jsonType($value), $types, true);
        if (! $valid || (isset($property['enum']) && ! in_array($value, $property['enum'], true))) {
            throw new InvalidArgumentException('Tool argument does not match its schema.');
        }

        if (is_string($value) && mb_strlen($value) > min(2000, (int) ($property['maxLength'] ?? 2000))) {
            throw new InvalidArgumentException('Tool string argument exceeds its maximum length.');
        }

        if (is_numeric($value) && (isset($property['minimum']) && $value < $property['minimum']
            || isset($property['maximum']) && $value > $property['maximum'])) {
            throw new InvalidArgumentException('Tool numeric argument is outside its allowed range.');
        }
    }

    private function jsonType(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_string($value) => 'string',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_bool($value) => 'boolean',
            is_array($value) && array_is_list($value) => 'array',
            is_array($value) => 'object',
            default => 'unsupported',
        };
    }
}
