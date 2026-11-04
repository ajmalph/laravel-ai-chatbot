<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Pgs\LaravelAiChatbot\Knowledge\KnowledgeSourceItem;
use Pgs\LaravelAiChatbot\Knowledge\KnowledgeSourceRegistry;
use Pgs\LaravelAiChatbot\Models\KnowledgeChunk;

final class KnowledgeIndexService
{
    public function __construct(
        private readonly KnowledgeSourceRegistry $sources,
        private readonly ConnectionInterface $database,
        private readonly ContentSanitizer $sanitizer,
        private readonly TextChunker $chunker,
    ) {}

    /**
     * @return array{sources: int, documents: int, chunks: int, deleted: int}
     */
    public function sync(?string $source = null, ?string $locale = null, bool $fresh = false): array
    {
        $names = $source === null ? $this->sources->names() : [$source];
        $summary = ['sources' => 0, 'documents' => 0, 'chunks' => 0, 'deleted' => 0];

        foreach ($names as $name) {
            $result = $this->syncSource($name, $locale, $fresh);
            $summary['sources']++;
            $summary['documents'] += $result['documents'];
            $summary['chunks'] += $result['chunks'];
            $summary['deleted'] += $result['deleted'];
        }

        return $summary;
    }

    /** @return array{documents: int, chunks: int, deleted: int} */
    public function syncSource(string $source, ?string $locale = null, bool $fresh = false): array
    {
        $adapter = $this->sources->get($source);
        $locales = $this->locales($locale);
        $token = (string) Str::uuid();
        $deleted = 0;

        if ($fresh) {
            $deleted += $this->scopedQuery($source, null, $locale)->delete();
        }

        $documents = 0;
        $chunks = 0;

        // Stale records are removed only after iteration finishes without throwing.
        foreach ($adapter->iterate($this->sourceBatchSize()) as $item) {
            if (! $item->isPublic) {
                $deleted += $this->invalidate($source, $item->id, $locale);

                continue;
            }

            $documents++;
            $result = $this->indexItem($source, $item, $locales, $token);
            $chunks += $result['chunks'];
            $deleted += $result['deleted'];
        }

        $deleted += $this->deleteNotSeen($source, $locales, $token);

        return compact('documents', 'chunks', 'deleted');
    }

    /**
     * Incrementally synchronize one record, revalidating its current public visibility.
     *
     * @return array{documents: int, chunks: int, deleted: int}
     */
    public function syncRecord(string $source, string $sourceId, ?string $locale = null): array
    {
        $item = $this->sources->get($source)->find($sourceId);

        if ($item === null || ! $item->isPublic) {
            return ['documents' => 0, 'chunks' => 0, 'deleted' => $this->invalidate($source, $sourceId)];
        }

        $result = $this->indexItem($source, $item, $this->locales($locale), (string) Str::uuid());

        return ['documents' => 1, ...$result];
    }

    /**
     * Retrieve indexed chunks only after checking that the originating CMS record is still public.
     *
     * @return Collection<int, KnowledgeChunk>
     */
    public function chunksFor(string $source, string $sourceId, ?string $locale = null): Collection
    {
        $item = $this->sources->get($source)->find($sourceId);

        if ($item === null || ! $item->isPublic) {
            $this->invalidate($source, $sourceId);

            return collect();
        }

        // Re-indexing is hash-idempotent and avoids trusting database/application timezone conversions.
        $this->indexItem($source, $item, $this->locales($locale), (string) Str::uuid());

        return $this->scopedQuery($source, $sourceId, $locale)
            ->orderBy('locale')
            ->orderBy('chunk_index')
            ->get();
    }

    public function invalidate(string $source, string $sourceId, ?string $locale = null): int
    {
        return $this->scopedQuery($source, $sourceId, $locale)->delete();
    }

    /** @param list<string> $locales
     * @return array{chunks: int, deleted: int}
     */
    private function indexItem(string $source, KnowledgeSourceItem $item, array $locales, string $token): array
    {
        return $this->database->transaction(function () use ($source, $item, $locales, $token): array {
            $rows = [];
            $now = now();

            foreach ($locales as $locale) {
                $title = $this->sanitizer->sanitize($item->title($locale));
                $body = $this->sanitizer->sanitize($item->body($locale));
                $content = $body !== '' ? $body : $title;
                $url = $item->url($locale);
                $parts = $this->chunker->split($content, $this->chunkSize(), $this->chunkOverlap());

                foreach ($parts as $index => $part) {
                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'source_type' => $source,
                        'source_id' => $item->id,
                        'external_id' => "{$source}:{$item->id}:{$locale}:{$index}",
                        'locale' => $locale,
                        'chunk_index' => $index,
                        'title' => $title,
                        'content' => $part,
                        'url' => $url,
                        'content_hash' => hash('sha256', implode("\0", [$title, $part, $locale, $url])),
                        'source_modified_at' => $item->modifiedAt->format('Y-m-d H:i:s'),
                        'sync_token' => $token,
                        'metadata' => json_encode([
                            'source' => $source,
                            'source_id' => $item->id,
                            'locale' => $locale,
                            'modified_at' => $item->modifiedAt->format(DATE_ATOM),
                        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($rows !== []) {
                KnowledgeChunk::query()->upsert(
                    $rows,
                    ['source_type', 'source_id', 'locale', 'chunk_index'],
                    ['external_id', 'title', 'content', 'url', 'content_hash', 'source_modified_at', 'sync_token', 'metadata', 'updated_at'],
                );
            }

            $deleted = $this->scopedQuery($source, $item->id)
                ->whereIn('locale', $locales)
                ->where(fn ($query) => $query->whereNull('sync_token')->orWhere('sync_token', '!=', $token))
                ->delete();

            return ['chunks' => count($rows), 'deleted' => $deleted];
        });
    }

    /** @param list<string> $locales */
    private function deleteNotSeen(string $source, array $locales, string $token): int
    {
        return $this->scopedQuery($source)
            ->whereIn('locale', $locales)
            ->where(fn ($query) => $query->whereNull('sync_token')->orWhere('sync_token', '!=', $token))
            ->delete();
    }

    private function scopedQuery(string $source, ?string $sourceId = null, ?string $locale = null)
    {
        return KnowledgeChunk::query()
            ->where('source_type', $source)
            ->when($sourceId !== null, fn ($query) => $query->where('source_id', $sourceId))
            ->when($locale !== null, fn ($query) => $query->where('locale', $locale));
    }

    /** @return list<string> */
    private function locales(?string $locale): array
    {
        $supported = array_values(config('ai-chatbot.index.locales', ['en', 'ar']));
        if ($locale !== null && ! in_array($locale, $supported, true)) {
            throw new InvalidArgumentException("Unsupported index locale [{$locale}].");
        }

        return $locale === null ? $supported : [$locale];
    }

    private function chunkSize(): int
    {
        return max(1, (int) config('ai-chatbot.index.chunk_size', 2000));
    }

    private function chunkOverlap(): int
    {
        return max(0, min($this->chunkSize() - 1, (int) config('ai-chatbot.index.chunk_overlap', 200)));
    }

    private function sourceBatchSize(): int
    {
        return max(1, (int) config('ai-chatbot.index.source_batch_size', 100));
    }
}
