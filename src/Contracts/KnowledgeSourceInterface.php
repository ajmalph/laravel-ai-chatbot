<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Contracts;

use Pgs\LaravelAiChatbot\Knowledge\KnowledgeSourceItem;

interface KnowledgeSourceInterface
{
    /**
     * Yield public items in stable identity order without loading the full source into memory.
     *
     * @return iterable<KnowledgeSourceItem>
     */
    public function iterate(int $chunkSize = 100): iterable;

    /**
     * Return the item only while it remains public; null means absent or no longer visible.
     */
    public function find(string $sourceId): ?KnowledgeSourceItem;
}
