<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\KnowledgeSources;

use App\Models\Admodels\PostModel;
use Illuminate\Database\Eloquent\Builder;
use Pgs\LaravelAiChatbot\Contracts\KnowledgeSourceInterface;
use Pgs\LaravelAiChatbot\Knowledge\KnowledgeSourceItem;

abstract class PostKnowledgeSource implements KnowledgeSourceInterface
{
    /** @return list<string> */
    abstract protected function postTypes(): array;

    abstract protected function canonicalPath(PostModel $post, string $locale): string;

    public function iterate(int $chunkSize = 100): iterable
    {
        $chunkSize = max(1, $chunkSize);

        foreach ($this->publicQuery()->lazyById($chunkSize, 'post_id', 'post_id') as $post) {
            yield $this->toKnowledgeItem($post);
        }
    }

    public function find(string $sourceId): ?KnowledgeSourceItem
    {
        if (! ctype_digit($sourceId)) {
            return null;
        }

        $post = $this->publicQuery()->whereKey((int) $sourceId)->first();

        return $post instanceof PostModel ? $this->toKnowledgeItem($post) : null;
    }

    protected function publicQuery(): Builder
    {
        return PostModel::query()
            ->whereIn('post_type', $this->postTypes())
            ->where('post_status', 1)
            ->whereNull('deleted_at')
            ->orderBy('post_id');
    }

    protected function toKnowledgeItem(PostModel $post): KnowledgeSourceItem
    {
        return new KnowledgeSourceItem(
            id: (string) $post->getAttribute('post_id'),
            isPublic: (int) $post->getAttribute('post_status') === 1 && $post->getAttribute('deleted_at') === null,
            titles: [
                'en' => $this->plainText($post->getAttribute('post_title')),
                'ar' => $this->plainText($post->getAttribute('post_title_arabic')),
            ],
            bodies: [
                'en' => $this->body($post, 'en'),
                'ar' => $this->body($post, 'ar'),
            ],
            urls: [
                'en' => url($this->canonicalPath($post, 'en')),
                'ar' => url($this->canonicalPath($post, 'ar')),
            ],
            modifiedAt: $post->getAttribute($post->getUpdatedAtColumn()),
            metadata: $this->metadata($post),
        );
    }

    /** @return array<string, mixed> */
    protected function metadata(PostModel $post): array
    {
        return [];
    }

    protected function body(PostModel $post, string $locale): string
    {
        return $this->plainText($post->getMeta($locale === 'ar' ? 'description_arabic' : 'description'));
    }

    protected function plainText(mixed $value): string
    {
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
