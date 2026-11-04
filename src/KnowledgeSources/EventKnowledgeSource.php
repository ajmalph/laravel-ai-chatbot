<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\KnowledgeSources;

use App\Models\Admodels\PostModel;

final class EventKnowledgeSource extends PostKnowledgeSource
{
    protected function postTypes(): array
    {
        return ['events'];
    }

    protected function canonicalPath(PostModel $post, string $locale): string
    {
        return "/{$locale}/events/{$post->post_slug}";
    }

    protected function metadata(PostModel $post): array
    {
        return ['event_date' => $post->getMeta('event_date')];
    }
}
