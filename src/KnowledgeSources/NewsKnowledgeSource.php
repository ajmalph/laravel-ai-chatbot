<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\KnowledgeSources;

use App\Models\Admodels\PostModel;

final class NewsKnowledgeSource extends PostKnowledgeSource
{
    protected function postTypes(): array
    {
        return ['news'];
    }

    protected function canonicalPath(PostModel $post, string $locale): string
    {
        return "/{$locale}/news/{$post->post_slug}";
    }
}
