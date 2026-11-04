<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Requests\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class PredefinedQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('question')?->getKey();

        return [
            'category_id' => ['required', 'integer', Rule::exists('ai_chatbot_question_categories', 'id')],
            'question_en' => ['required', 'string', 'max:2000'], 'question_ar' => ['required', 'string', 'max:2000'],
            'answer_en' => ['required', 'string', 'max:20000'], 'answer_ar' => ['required', 'string', 'max:20000'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'cms_page_type' => ['nullable', 'string', 'max:255', Rule::in((array) config('ai-chatbot.predefined.cms_page_types', []))],
            'cms_page_id' => ['nullable', 'integer', 'min:1', 'required_with:cms_page_type', 'prohibited_without:cms_page_type'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'], 'is_active' => ['required', 'boolean'],
            'followup_ids' => ['array', 'max:20'],
            'followup_ids.*' => ['integer', 'distinct', Rule::exists('ai_chatbot_predefined_questions', 'id'), Rule::notIn(array_filter([$id]))],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = $this->input('cms_page_type');
            $id = $this->integer('cms_page_id');
            if (! is_string($type) || $type === '' || $id < 1 || ! class_exists($type) || ! is_subclass_of($type, Model::class) || ! $type::query()->whereKey($id)->exists()) {
                if ($type || $id) {
                    $validator->errors()->add('cms_page_id', 'The selected CMS page does not exist.');
                }
            }
        }];
    }
}
