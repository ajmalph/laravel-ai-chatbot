<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['limit' => ['sometimes', 'integer', 'min:1', 'max:100'], 'before' => ['sometimes', 'date']];
    }
}
