# Knowledge source adapters

The package knows nothing about a host CMS. A host application opts in by implementing `Pgs\LaravelAiChatbot\Contracts\KnowledgeSourceInterface` and explicitly registering a trusted adapter.

```php
final class PageKnowledgeSource implements KnowledgeSourceInterface
{
    public function iterate(int $chunkSize = 100): iterable
    {
        foreach (Page::query()
            ->where('status', 'published')
            ->where('is_public', true)
            ->lazyById(max(1, $chunkSize)) as $page) {
            yield $this->map($page);
        }
    }

    public function find(string $sourceId): ?KnowledgeSourceItem
    {
        $page = Page::query()
            ->whereKey($sourceId)
            ->where('status', 'published')
            ->where('is_public', true)
            ->first();

        return $page ? $this->map($page) : null;
    }
}
```

Register it in the application's service provider:

```php
AiChatbot::registerKnowledgeSource('pages', PageKnowledgeSource::class);
```

`find()` is deliberately visibility-aware. A later indexing process can retrieve an item again by stable ID; a `null` result means the record was deleted, unpublished, made private, or never existed and should no longer be usable as public knowledge.

## Contract rules

- IDs must be stable within the registered source. The source name and item ID together form the global identity.
- `iterate()` must use pagination, cursor iteration, or chunked iteration and must return public records only.
- `find()` must re-run current visibility constraints and return `null` for non-public records.
- Both languages are explicit: `titles`, `bodies`, and `urls` contain exactly `en` and `ar`. Empty translated content is allowed; silently substituting one language for another is not.
- URLs must be canonical public URLs, not admin, preview, signed, or private URLs.
- `modifiedAt` must change whenever indexable content changes.

## This host application

At the host's request, its adapters live inside the package under `Pgs\LaravelAiChatbot\KnowledgeSources`. They use the real `PostModel` fields: `post_id`, `post_type`, `post_status`, `post_title`, `post_title_arabic`, `post_slug`, `post_updated_at`, and the `description` / `description_arabic` metadata keys.

Every query requires `post_status = 1` and `deleted_at IS NULL`. Five adapters are registered explicitly: pages, news, FAQs, initiatives (the host's `projects` post type), and publications (the host's `knowledge-hub` post type). The FAQ adapter is supported but yields nothing unless this host actually creates public `faq` records; no model or observer is inferred.

These bundled adapters are enabled by default for this installation. Other Laravel projects should set `AI_CHATBOT_REGISTER_BUNDLED_SOURCES=false` and explicitly register their own CMS adapters.
