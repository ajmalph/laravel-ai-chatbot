<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'role' => $this->role,
            'content' => $this->content,
            'status' => $this->status,
            'locale' => $metadata['locale'] ?? null,
            'sources' => $metadata['sources'] ?? [],
            'followups' => $metadata['followups'] ?? [],
            'predefined_question_id' => $metadata['predefined_question_id'] ?? null,
            'fallback' => (bool) ($metadata['fallback'] ?? false),
            'created_at' => $this->created_at?->toAtomString(),
        ];
    }
}
