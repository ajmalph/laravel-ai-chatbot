<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\KnowledgeSources;

use App\Models\Admodels\PostModel;

final class PageKnowledgeSource extends PostKnowledgeSource
{
    protected function postTypes(): array
    {
        return ['about', 'privacy-policy', 'terms-and-conditions', 'strategies'];
    }

    protected function canonicalPath(PostModel $post, string $locale): string
    {
        return match ($post->post_type) {
            'privacy-policy' => "/{$locale}/privacy-policy",
            'terms-and-conditions' => "/{$locale}/terms-and-conditions",
            default => "/{$locale}/#{$post->post_slug}",
        };
    }
}
