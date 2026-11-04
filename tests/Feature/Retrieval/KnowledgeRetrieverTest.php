<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tests\Feature\Retrieval;

use InvalidArgumentException;
use Pgs\LaravelAiChatbot\Contracts\KnowledgeRetrieverInterface;
use Pgs\LaravelAiChatbot\Knowledge\KnowledgeSourceRegistry;
use Pgs\LaravelAiChatbot\Models\KnowledgeChunk;
use Pgs\LaravelAiChatbot\Retrieval\ContextBuilder;
use Pgs\LaravelAiChatbot\Retrieval\MySqlKnowledgeRetriever;
use Pgs\LaravelAiChatbot\Services\KnowledgeIndexService;
use Pgs\LaravelAiChatbot\Tests\Fixtures\ArrayKnowledgeSource;
use Pgs\LaravelAiChatbot\Tests\TestCase;

final class KnowledgeRetrieverTest extends TestCase
{
    private ArrayKnowledgeSource $source;

    private KnowledgeRetrieverInterface $retriever;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ai-chatbot.index.chunk_size' => 45,
            'ai-chatbot.index.chunk_overlap' => 10,
            'ai-chatbot.retrieval.fallback_candidate_limit' => 50,
        ]);

        $this->source = new ArrayKnowledgeSource;
        $this->source->records['published']['title_en'] = 'Cabinet climate strategy';
        $this->source->records['published']['title_ar'] = 'استراتيجية المناخ الحكومية';
        $this->source->records['published']['body_en'] = str_repeat('The cabinet approved a climate strategy. ', 5);
        $this->source->records['published']['body_ar'] = str_repeat('اعتمد مجلس الوزراء استراتيجية المناخ. ', 5);
        $this->source->records['secondary'] = [
            'public' => true,
            'title_en' => 'General government update',
            'title_ar' => 'تحديث حكومي عام',
            'body_en' => 'A cabinet meeting mentioned climate once.',
            'body_ar' => 'اجتماع حكومي ذكر المناخ مرة واحدة.',
            'modified_at' => '2026-10-19T12:00:00+04:00',
        ];

        app(KnowledgeSourceRegistry::class)->register('pages', $this->source);
        app(KnowledgeIndexService::class)->syncSource('pages');
        $this->retriever = app(KnowledgeRetrieverInterface::class);
    }

    public function test_default_driver_is_replaceable_mysql_retriever(): void
    {
        $this->assertInstanceOf(MySqlKnowledgeRetriever::class, $this->retriever);
    }

    public function test_english_results_are_ranked_and_overlapping_chunks_are_deduplicated(): void
    {
        $results = $this->retriever->retrieve('cabinet climate strategy', 'en', ['pages'], 5);

        $this->assertCount(2, $results);
        $this->assertSame('published', $results[0]->sourceId);
        $this->assertGreaterThan($results[1]->score, $results[0]->score);
        $this->assertSame('Cabinet climate strategy', $results[0]->title);
        $this->assertSame('/en/content/published', $results[0]->url);
        $this->assertSame(
            count($results),
            count(array_unique(array_map(fn ($result) => $result->source.':'.$result->sourceId, $results))),
        );
    }

    public function test_arabic_search_preserves_language_and_applies_locale_filter(): void
    {
        $results = $this->retriever->retrieve('استراتيجية المناخ', 'ar', ['pages']);

        $this->assertNotEmpty($results);
        $this->assertSame('ar', $results[0]->locale);
        $this->assertSame('استراتيجية المناخ الحكومية', $results[0]->title);
        $this->assertStringContainsString('المناخ', $results[0]->content);
        $this->assertSame('/ar/content/published', $results[0]->url);
    }

    public function test_short_terms_use_bounded_fallback_and_missing_content_returns_empty(): void
    {
        config(['ai-chatbot.retrieval.fallback_candidate_limit' => 1]);

        $this->assertNotEmpty($this->retriever->retrieve('a', 'en', ['pages']));
        $this->assertSame([], $this->retriever->retrieve('not-present-anywhere', 'en', ['pages']));
        $this->assertSame([], $this->retriever->retrieve('', 'en', ['pages']));
        $this->assertSame([], $this->retriever->retrieve('cabinet', 'fr', ['pages']));
    }

    public function test_unpublished_content_is_revalidated_removed_and_never_returned(): void
    {
        $this->source->records['published']['public'] = false;

        $results = $this->retriever->retrieve('cabinet climate strategy', 'en', ['pages']);

        $this->assertFalse(collect($results)->contains(fn ($result) => $result->sourceId === 'published'));
        $this->assertSame(0, KnowledgeChunk::query()->where('source_id', 'published')->count());
    }

    public function test_source_filters_must_reference_registered_sources(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->retriever->retrieve('cabinet', 'en', ['private-or-unknown']);
    }

    public function test_context_builder_enforces_a_hard_character_limit(): void
    {
        $results = $this->retriever->retrieve('climate', 'en', ['pages']);
        $context = app(ContextBuilder::class)->build($results, 120);

        $this->assertLessThanOrEqual(120, mb_strlen($context));
        $this->assertStringContainsString('Source:', $context);
    }
}
