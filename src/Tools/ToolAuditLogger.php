<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tools;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Str;
use Throwable;

final class ToolAuditLogger
{
    public function __construct(private readonly ConnectionResolverInterface $database) {}

    /** @param list<string> $argumentKeys */
    public function record(string $tool, string $status, array $argumentKeys, ?string $errorCode, int $durationMilliseconds): void
    {
        if (! (bool) config('ai-chatbot.tools.audit', true)) {
            return;
        }

        try {
            $this->database->connection()->table(config('ai-chatbot.tables.tool_logs'))->insert([
                'id' => (string) Str::uuid7(),
                'conversation_id' => null,
                'message_id' => null,
                'tool' => mb_substr($tool, 0, 255),
                'status' => mb_substr($status, 0, 32),
                'arguments' => json_encode(['keys' => $argumentKeys], JSON_THROW_ON_ERROR),
                'result' => null,
                'error' => $errorCode,
                'duration_ms' => max(0, $durationMilliseconds),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
            // Audit storage must not expose tool data or make a public read-only tool unavailable.
        }
    }
}
