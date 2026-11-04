<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class PredefinedQuestion extends Model
{
    protected $table = 'ai_chatbot_predefined_questions';

    protected $fillable = ['category_id', 'question_en', 'question_ar', 'answer_en', 'answer_ar', 'source_url', 'cms_page_type', 'cms_page_id', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(QuestionCategory::class, 'category_id');
    }

    public function cmsPage(): MorphTo
    {
        return $this->morphTo();
    }

    public function followups(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'ai_chatbot_question_followups', 'question_id', 'followup_question_id')->withPivot('sort_order')->orderByPivot('sort_order');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereHas('category', fn (Builder $category) => $category->active());
    }
}
