<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Contracts;

use Pgs\LaravelAiChatbot\Retrieval\RetrievalResult;

interface KnowledgeRetrieverInterface
{
    /**
     * @param  list<string>  $sources  Empty means all registered sources.
     * @return list<RetrievalResult>
     */
    public function retrieve(string $query, string $locale = 'en', array $sources = [], int $limit = 5): array;
}
