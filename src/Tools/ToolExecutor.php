<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tools;

use Pgs\LaravelAiChatbot\Data\ToolResult;
use Throwable;

final class ToolExecutor
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly ToolSchemaValidator $schemas,
        private readonly ToolAuthorizationService $authorization,
        private readonly ToolAuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $arguments */
    public function execute(
        string $name,
        array $arguments,
        string $callId,
        mixed $actor,
        ToolExecutionBudget $budget,
    ): ToolResult {
        $started = hrtime(true);
        $status = 'failed';
        $error = null;
        $output = '';

        try {
            if (! $budget->claimCall()) {
                $error = 'call_limit_exceeded';

                return $this->result($callId, $name, $status, $error, $started, $arguments);
            }

            $argumentJson = json_encode($arguments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $argumentLimit = max(2, min(20_000, (int) config('ai-chatbot.tools.max_argument_characters', 4000)));
            if (mb_strlen($argumentJson) > $argumentLimit) {
                $error = 'arguments_too_large';

                return $this->result($callId, $name, $status, $error, $started, $arguments);
            }

            if (! $this->registry->has($name)) {
                $error = 'unknown_tool';

                return $this->result($callId, $name, $status, $error, $started, $arguments);
            }

            $tool = $this->registry->get($name);
            $arguments = $this->schemas->validateArguments($arguments, $tool->argumentsSchema());

            if (! $this->authorization->allows($tool, $actor)) {
                $status = 'denied';
                $error = 'unauthorized';

                return $this->result($callId, $name, $status, $error, $started, $arguments);
            }

            $raw = $tool->executeReadOnly($arguments);
            $encoded = json_encode($this->sanitize($raw), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $limit = min(
                max(1, $tool->maximumResultCharacters()),
                max(0, $budget->remainingResultCharacters()),
                max(1, min(50_000, (int) config('ai-chatbot.tools.max_result_characters', 8000))),
            );

            if ($limit < 1) {
                $error = 'result_limit_exceeded';

                return $this->result($callId, $name, $status, $error, $started, $arguments);
            }

            $output = mb_strlen($encoded) <= $limit
                ? $encoded
                : mb_substr('Tool result truncated: '.$encoded, 0, $limit);
            $budget->recordResult($output);
            $status = 'completed';
        } catch (\InvalidArgumentException) {
            $error = 'invalid_arguments';
        } catch (Throwable) {
            $error = 'execution_failed';
        }

        return $this->result($callId, $name, $status, $error, $started, $arguments, $output);
    }

    /** @param array<string, mixed> $arguments */
    private function result(string $callId, string $name, string $status, ?string $error, int $started, array $arguments, string $output = ''): ToolResult
    {
        $duration = (int) ((hrtime(true) - $started) / 1_000_000);
        $safeOutput = $error === null ? $output : json_encode(['ok' => false, 'error' => $error], JSON_THROW_ON_ERROR);
        $this->audit->record($name, $status, array_values(array_filter(array_keys($arguments), 'is_string')), $error, $duration);

        return new ToolResult($callId, $name, $status, $safeOutput, $error, $duration);
    }

    private function sanitize(mixed $value): mixed
    {
        if (is_string($value)) {
            return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        if (is_array($value)) {
            return array_map($this->sanitize(...), $value);
        }

        return is_scalar($value) || $value === null ? $value : (string) $value;
    }
}
