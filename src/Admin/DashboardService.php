<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Admin;

use Illuminate\Database\ConnectionInterface;

final class DashboardService
{
    public function __construct(private readonly ConnectionInterface $database) {}

    public function metrics(): array
    {
        $conversations = config('ai-chatbot.tables.conversations');
        $messages = config('ai-chatbot.tables.messages');
        $row = $this->database->table($messages)->where('role', 'assistant')->selectRaw(
            'COUNT(*) as messages, SUM(CASE WHEN error_code IS NOT NULL THEN 1 ELSE 0 END) as failures, AVG(latency_ms) as latency, COALESCE(SUM(input_tokens), 0) as input_tokens, COALESCE(SUM(output_tokens), 0) as output_tokens, COALESCE(SUM(estimated_cost_usd), 0) as cost'
        )->first();

        return [
            'conversations' => $this->database->table($conversations)->whereNull('deleted_at')->count(),
            'messages' => (int) ($row->messages ?? 0),
            'api_failures' => (int) ($row->failures ?? 0),
            'average_latency_ms' => (int) round((float) ($row->latency ?? 0)),
            'input_tokens' => (int) ($row->input_tokens ?? 0),
            'output_tokens' => (int) ($row->output_tokens ?? 0),
            'estimated_cost_usd' => round((float) ($row->cost ?? 0), 8),
        ];
    }
}
