<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'string', Rule::in(['en', 'ar'])],
            'title' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
