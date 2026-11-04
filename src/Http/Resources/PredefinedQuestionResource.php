<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PredefinedQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->string('locale', app()->getLocale())->lower()->value() === 'ar' ? 'ar' : 'en';

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'question' => $this->{"question_{$locale}"},
            'answer' => $this->{"answer_{$locale}"},
            'source_url' => $this->source_url,
            'followups' => $this->whenLoaded('followups', fn () => $this->followups->map(fn ($followup) => ['id' => $followup->id, 'question' => $followup->{"question_{$locale}"}])->values()),
        ];
    }
}
