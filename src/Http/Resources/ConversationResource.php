<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'status' => $this->status, 'expires_at' => $this->expires_at?->toAtomString(), 'created_at' => $this->created_at?->toAtomString()];
    }
}
