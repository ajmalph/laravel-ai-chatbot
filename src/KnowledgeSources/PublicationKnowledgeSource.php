<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\KnowledgeSources;

use App\Models\Admodels\PostModel;

final class PublicationKnowledgeSource extends PostKnowledgeSource
{
    protected function postTypes(): array
    {
        return ['knowledge-hub'];
    }

    protected function canonicalPath(PostModel $post, string $locale): string
    {
        return "/{$locale}/knowledge-hub/download/{$post->post_slug}";
    }
}
