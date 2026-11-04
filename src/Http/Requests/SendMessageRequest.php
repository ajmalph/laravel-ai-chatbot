<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maximum = max(1, min(10_000, (int) config('ai-chatbot.api.max_message_characters', 1000)));

        return [
            'conversation_id' => ['required', 'uuid'],
            'idempotency_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'message' => ['required', 'string', "max:{$maximum}"],
            'predefined_question_id' => ['sometimes', 'integer', 'min:1'],
            'locale' => ['sometimes', 'string', Rule::in(['en', 'ar'])],
            'sources' => ['sometimes', 'array', 'max:10'],
            'sources.*' => ['string', 'max:100', 'regex:/^[a-z0-9_-]+$/'],
        ];
    }
}
