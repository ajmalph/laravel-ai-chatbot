<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tests\Fixtures;

use DateTimeImmutable;
use Pgs\LaravelAiChatbot\Contracts\KnowledgeSourceInterface;
use Pgs\LaravelAiChatbot\Knowledge\KnowledgeSourceItem;

final class ArrayKnowledgeSource implements KnowledgeSourceInterface
{
    public bool $failDuringIteration = false;

    /** @var array<string, array<string, mixed>> */
    public array $records = [
        'published' => [
            'public' => true,
            'title_en' => 'English title',
            'title_ar' => 'العنوان العربي',
            'body_en' => 'English body',
            'body_ar' => 'النص العربي',
            'modified_at' => '2026-10-20T12:00:00+04:00',
        ],
        'draft' => [
            'public' => false,
            'title_en' => 'Draft',
            'title_ar' => 'مسودة',
            'body_en' => 'Private',
            'body_ar' => 'خاص',
            'modified_at' => '2026-10-20T12:00:00+04:00',
        ],
    ];

    public function iterate(int $chunkSize = 100): iterable
    {
        $yielded = 0;
        foreach (array_chunk($this->records, max(1, $chunkSize), true) as $chunk) {
            foreach ($chunk as $id => $record) {
                if ($record['public']) {
                    yield $this->map($id, $record);
                    $yielded++;
                    if ($this->failDuringIteration && $yielded === 1) {
                        throw new \RuntimeException('Simulated source failure.');
                    }
                }
            }
        }
    }

    public function find(string $sourceId): ?KnowledgeSourceItem
    {
        $record = $this->records[$sourceId] ?? null;

        return $record !== null && $record['public'] ? $this->map($sourceId, $record) : null;
    }

    /** @param array<string, mixed> $record */
    private function map(string $id, array $record): KnowledgeSourceItem
    {
        return new KnowledgeSourceItem(
            id: $id,
            isPublic: true,
            titles: ['en' => $record['title_en'], 'ar' => $record['title_ar']],
            bodies: ['en' => $record['body_en'], 'ar' => $record['body_ar']],
            urls: ['en' => "/en/content/{$id}", 'ar' => "/ar/content/{$id}"],
            modifiedAt: new DateTimeImmutable($record['modified_at']),
        );
    }
}
