<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Retrieval;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Pgs\LaravelAiChatbot\Contracts\KnowledgeRetrieverInterface;
use Pgs\LaravelAiChatbot\Knowledge\KnowledgeSourceRegistry;
use Pgs\LaravelAiChatbot\Models\KnowledgeChunk;
use Pgs\LaravelAiChatbot\Services\KnowledgeIndexService;
use Throwable;

final class MySqlKnowledgeRetriever implements KnowledgeRetrieverInterface
{
    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly KnowledgeSourceRegistry $sources,
        private readonly KnowledgeIndexService $index,
        private readonly RelevanceRankingService $ranking,
    ) {}

    public function retrieve(string $query, string $locale = 'en', array $sources = [], int $limit = 5): array
    {
        $query = mb_substr($this->ranking->normalize($query), 0, $this->maxQueryCharacters());
        $limit = max(1, min($limit, $this->maxResults()));
        $sourceNames = $this->validatedSources($sources);

        if ($query === '' || $sourceNames === [] || ! in_array($locale, $this->locales(), true)) {
            return [];
        }

        $candidates = $this->fullTextIsAppropriate($query)
            ? $this->fullTextCandidates($query, $locale, $sourceNames, $limit)
            : null;
        if ($candidates === null || (is_countable($candidates) && count($candidates) === 0)) {
            $candidates = $this->boundedCandidates($locale, $sourceNames);
        }

        $documents = [];
        foreach ($candidates as $candidate) {
            $key = $candidate->source_type."\0".$candidate->source_id;
            $documents[$key] ??= $candidate;
            $documents[$key]->engine_score = max(
                (float) ($documents[$key]->engine_score ?? 0),
                (float) ($candidate->engine_score ?? 0),
            );
        }

        $results = [];
        foreach ($documents as $candidate) {
            try {
                // This re-queries the CMS adapter, removes invisible records, and refreshes modified chunks.
                $chunks = $this->index->chunksFor($candidate->source_type, (string) $candidate->source_id, $locale);
            } catch (Throwable) {
                continue;
            }

            $best = null;
            $bestScore = 0.0;
            foreach ($chunks as $chunk) {
                $lexicalScore = $this->ranking->score($query, (string) $chunk->title, (string) $chunk->content);
                if ($lexicalScore <= 0.0) {
                    continue;
                }

                $score = $lexicalScore + (float) ($candidate->engine_score ?? 0);
                if ($score > $bestScore) {
                    $best = $chunk;
                    $bestScore = $score;
                }
            }

            if ($best === null) {
                continue;
            }

            $results[] = new RetrievalResult(
                source: (string) $best->source_type,
                sourceId: (string) $best->source_id,
                locale: (string) $best->locale,
                title: (string) $best->title,
                content: mb_substr((string) $best->content, 0, $this->maxResultCharacters()),
                url: (string) $best->url,
                score: $bestScore,
                metadata: $best->metadata ?? [],
            );
        }

        usort($results, static fn (RetrievalResult $left, RetrievalResult $right): int => $right->score <=> $left->score);

        return array_slice($results, 0, $limit);
    }

    /** @param list<string> $sources */
    private function fullTextCandidates(string $query, string $locale, array $sources, int $limit): ?iterable
    {
        try {
            return KnowledgeChunk::query()
                ->select(['source_type', 'source_id'])
                ->selectRaw('MAX(MATCH(title, content) AGAINST (? IN NATURAL LANGUAGE MODE)) AS engine_score', [$query])
                ->where('locale', $locale)
                ->whereIn('source_type', $sources)
                ->whereRaw('MATCH(title, content) AGAINST (? IN NATURAL LANGUAGE MODE)', [$query])
                ->groupBy('source_type', 'source_id')
                ->orderByDesc('engine_score')
                ->limit(min($this->candidateLimit(), $limit * 8))
                ->get();
        } catch (QueryException) {
            return null;
        }
    }

    /** @param list<string> $sources */
    private function boundedCandidates(string $locale, array $sources): iterable
    {
        return KnowledgeChunk::query()
            ->select(['source_type', 'source_id', 'source_modified_at'])
            ->selectRaw('0 AS engine_score')
            ->where('locale', $locale)
            ->whereIn('source_type', $sources)
            ->orderByDesc('source_modified_at')
            ->limit($this->candidateLimit())
            ->get();
    }

    private function fullTextIsAppropriate(string $query): bool
    {
        if ($this->database->getDriverName() !== 'mysql') {
            return false;
        }

        $minimum = max(1, (int) config('ai-chatbot.retrieval.fulltext_min_term_length', 3));

        return collect($this->ranking->terms($query))->contains(
            static fn (string $term): bool => mb_strlen($term) >= $minimum,
        );
    }

    /** @param list<string> $requested
     * @return list<string>
     */
    private function validatedSources(array $requested): array
    {
        $registered = $this->sources->names();
        if ($requested === []) {
            return $registered;
        }

        foreach ($requested as $source) {
            if (! is_string($source) || ! in_array($source, $registered, true)) {
                throw new InvalidArgumentException('Retrieval source filters must contain registered source names only.');
            }
        }

        return array_values(array_unique($requested));
    }

    /** @return list<string> */
    private function locales(): array
    {
        return array_values(config('ai-chatbot.index.locales', ['en', 'ar']));
    }

    private function candidateLimit(): int
    {
        return max(1, min(500, (int) config('ai-chatbot.retrieval.fallback_candidate_limit', 100)));
    }

    private function maxResults(): int
    {
        return max(1, min(50, (int) config('ai-chatbot.retrieval.max_results', 8)));
    }

    private function maxQueryCharacters(): int
    {
        return max(1, (int) config('ai-chatbot.retrieval.max_query_characters', 500));
    }

    private function maxResultCharacters(): int
    {
        return max(1, (int) config('ai-chatbot.retrieval.max_result_characters', 2500));
    }
}
