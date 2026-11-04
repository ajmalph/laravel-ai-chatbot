<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Pgs\LaravelAiChatbot\Knowledge\KnowledgeSourceRegistry;

final class KnowledgeSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', 'string', Rule::in(app(KnowledgeSourceRegistry::class)->names())],
            'locale' => ['nullable', 'string', Rule::in(config('ai-chatbot.index.locales', ['en', 'ar']))],
        ];
    }
}
