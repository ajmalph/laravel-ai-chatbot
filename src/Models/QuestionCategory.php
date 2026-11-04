<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class QuestionCategory extends Model
{
    protected $table = 'ai_chatbot_question_categories';

    protected $fillable = ['slug', 'title_en', 'title_ar', 'description_en', 'description_ar', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(PredefinedQuestion::class, 'category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
