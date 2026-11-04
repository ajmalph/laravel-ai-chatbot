<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class QuestionCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->string('locale', app()->getLocale())->lower()->value() === 'ar' ? 'ar' : 'en';

        return ['id' => $this->id, 'title' => $this->{"title_{$locale}"}, 'description' => $this->{"description_{$locale}"}, 'questions_count' => $this->whenCounted('questions')];
    }
}
