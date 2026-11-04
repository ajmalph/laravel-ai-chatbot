<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'screen_title_en' => ['required', 'string', 'max:255'],
            'screen_title_ar' => ['required', 'string', 'max:255'],
            'welcome_en' => ['required', 'string', 'max:1000'], 'welcome_ar' => ['required', 'string', 'max:1000'],
            'fallback_en' => ['required', 'string', 'max:1000'], 'fallback_ar' => ['required', 'string', 'max:1000'],
            'default_followups_en' => ['present', 'array', 'max:10'],
            'default_followups_en.*' => ['required', 'string', 'max:500'],
            'default_followups_ar' => ['present', 'array', 'max:10'],
            'default_followups_ar.*' => ['required', 'string', 'max:500'],
            'predefined_questions_enabled' => ['required', 'boolean'],
            'retrieval_limit' => ['required', 'integer', 'min:1', 'max:20'],
            'retention_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'daily_message_budget' => ['required', 'integer', 'min:1', 'max:100000'],
            'daily_token_budget' => ['required', 'integer', 'min:1', 'max:100000000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['default_followups_en', 'default_followups_ar'] as $key) {
            $this->merge([$key => array_values(array_filter((array) $this->input($key, []), fn ($value) => is_string($value) && trim($value) !== ''))]);
        }
    }
}
