<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class KnowledgeChunk extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'source_modified_at' => 'immutable_datetime',
        ];
    }

    public function getTable(): string
    {
        return config('ai-chatbot.tables.knowledge_chunks', 'ai_chatbot_knowledge_chunks');
    }
}
