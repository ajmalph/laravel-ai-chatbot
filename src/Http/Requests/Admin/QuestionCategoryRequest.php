<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class QuestionCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('category')?->getKey();

        return [
            'slug' => ['required', 'string', 'max:160', Rule::unique('ai_chatbot_question_categories', 'slug')->ignore($id)],
            'title_en' => ['required', 'string', 'max:255'], 'title_ar' => ['required', 'string', 'max:255'],
            'description_en' => ['nullable', 'string', 'max:2000'], 'description_ar' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'], 'is_active' => ['required', 'boolean'],
        ];
    }
}
