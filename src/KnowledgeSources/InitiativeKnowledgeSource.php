<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\KnowledgeSources;

use App\Models\Admodels\PostModel;

final class InitiativeKnowledgeSource extends PostKnowledgeSource
{
    protected function postTypes(): array
    {
        return ['projects'];
    }

    protected function canonicalPath(PostModel $post, string $locale): string
    {
        return "/{$locale}/projects/{$post->post_slug}";
    }
}
