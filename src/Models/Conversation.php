<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Conversation extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['participant_type', 'participant_id', 'visitor_id_hash', 'credential_hash', 'title', 'status', 'metadata', 'last_message_at', 'expires_at'];

    protected $hidden = ['credential_hash', 'visitor_id_hash'];

    public function getTable(): string
    {
        return (string) config('ai-chatbot.tables.conversations', 'ai_chatbot_conversations');
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'last_message_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }
}
