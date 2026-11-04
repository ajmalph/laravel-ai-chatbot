<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Message extends Model
{
    use HasUuids;

    protected $fillable = ['conversation_id', 'client_request_id', 'response_to_message_id', 'role', 'content', 'status', 'provider', 'model', 'input_tokens', 'output_tokens', 'latency_ms', 'estimated_cost_usd', 'error_code', 'metadata'];

    public function getTable(): string
    {
        return (string) config('ai-chatbot.tables.messages', 'ai_chatbot_messages');
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'estimated_cost_usd' => 'decimal:8'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
