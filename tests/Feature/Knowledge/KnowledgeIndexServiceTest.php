<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tests\Feature\Knowledge;

use Pgs\LaravelAiChatbot\Jobs\SyncKnowledgeSourceJob;
use Pgs\LaravelAiChatbot\Knowledge\KnowledgeSourceRegistry;
use Pgs\LaravelAiChatbot\Models\KnowledgeChunk;
use Pgs\LaravelAiChatbot\Services\KnowledgeIndexService;
use Pgs\LaravelAiChatbot\Tests\Fixtures\ArrayKnowledgeSource;
use Pgs\LaravelAiChatbot\Tests\TestCase;
use RuntimeException;

final class KnowledgeIndexServiceTest extends TestCase
{
    private ArrayKnowledgeSource $source;

    private KnowledgeIndexService $index;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ai-chatbot.index.chunk_size' => 2000,
            'ai-chatbot.index.chunk_overlap' => 200,
        ]);

        $this->source = new ArrayKnowledgeSource;
        app(KnowledgeSourceRegistry::class)->register('test', $this->source);
        $this->index = app(KnowledgeIndexService::class);
    }

    public function test_it_indexes_english_and_arabic_content_with_metadata_and_hashes(): void
    {
        $result = $this->index->syncSource('test');

        $this->assertSame(1, $result['documents']);
        $this->assertSame(2, $result['chunks']);
        $this->assertDatabaseCount(config('ai-chatbot.tables.knowledge_chunks'), 2);
        $this->assertDatabaseHas(config('ai-chatbot.tables.knowledge_chunks'), [
            'source_type' => 'test',
            'source_id' => 'published',
            'locale' => 'en',
            'title' => 'English title',
            'content' => 'English body',
            'url' => '/en/content/published',
        ]);
        $arabic = KnowledgeChunk::query()->where('locale', 'ar')->firstOrFail();
        $this->assertSame('العنوان العربي', $arabic->title);
        $this->assertSame('النص العربي', $arabic->content);
        $this->assertSame(64, strlen($arabic->content_hash));
        $this->assertSame('ar', $arabic->metadata['locale']);
    }

    public function test_repeated_sync_is_idempotent_and_updates_modified_content(): void
    {
        $this->index->syncSource('test');
        $ids = KnowledgeChunk::query()->orderBy('locale')->pluck('id')->all();

        $this->index->syncSource('test');
        $this->assertSame($ids, KnowledgeChunk::query()->orderBy('locale')->pluck('id')->all());
        $this->assertDatabaseCount(config('ai-chatbot.tables.knowledge_chunks'), 2);

        $before = KnowledgeChunk::query()->where('locale', 'en')->value('content_hash');
        $this->source->records['published']['body_en'] = '<p>Updated public body</p>';
        $this->source->records['published']['modified_at'] = '2026-10-21T12:00:00+04:00';
        $this->index->syncRecord('test', 'published', 'en');

        $updated = KnowledgeChunk::query()->where('locale', 'en')->firstOrFail();
        $this->assertSame('Updated public body', $updated->content);
        $this->assertNotSame($before, $updated->content_hash);
    }

    public function test_deleted_and_unpublished_records_are_invalidated_during_retrieval(): void
    {
        $this->index->syncSource('test');
        $this->source->records['published']['public'] = false;

        $this->assertTrue($this->index->chunksFor('test', 'published')->isEmpty());
        $this->assertDatabaseCount(config('ai-chatbot.tables.knowledge_chunks'), 0);

        $this->source->records['published']['public'] = true;
        $this->index->syncRecord('test', 'published');
        unset($this->source->records['published']);

        $this->assertTrue($this->index->chunksFor('test', 'published')->isEmpty());
        $this->assertDatabaseCount(config('ai-chatbot.tables.knowledge_chunks'), 0);
    }

    public function test_reconciliation_removes_stale_records(): void
    {
        $this->index->syncSource('test');
        unset($this->source->records['published']);

        $result = $this->index->syncSource('test');

        $this->assertSame(2, $result['deleted']);
        $this->assertDatabaseCount(config('ai-chatbot.tables.knowledge_chunks'), 0);
    }

    public function test_large_multibyte_content_is_split_into_overlapping_chunks(): void
    {
        config(['ai-chatbot.index.chunk_size' => 40, 'ai-chatbot.index.chunk_overlap' => 10]);
        $this->source->records['published']['body_en'] = str_repeat('searchable content ', 20);
        $this->source->records['published']['body_ar'] = str_repeat('محتوى عربي قابل للبحث ', 20);

        $this->index->syncSource('test');

        $this->assertGreaterThan(2, KnowledgeChunk::query()->where('locale', 'en')->count());
        $this->assertGreaterThan(2, KnowledgeChunk::query()->where('locale', 'ar')->count());
        $this->assertTrue(KnowledgeChunk::query()->get()->every(
            static fn (KnowledgeChunk $chunk): bool => mb_strlen($chunk->content) <= 40,
        ));
    }

    public function test_source_failure_bubbles_for_queue_retry_without_stale_reconciliation(): void
    {
        $this->source->records['second'] = [
            'public' => true,
            'title_en' => 'Second', 'title_ar' => 'الثاني',
            'body_en' => 'Second body', 'body_ar' => 'النص الثاني',
            'modified_at' => '2026-10-20T12:00:00+04:00',
        ];
        $this->index->syncSource('test');
        $this->assertDatabaseCount(config('ai-chatbot.tables.knowledge_chunks'), 4);

        $this->source->failDuringIteration = true;
        $job = new SyncKnowledgeSourceJob('test');

        $this->assertSame(3, $job->tries);
        try {
            $job->handle($this->index);
            $this->fail('The failed source job should throw for Laravel retry handling.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated source failure.', $exception->getMessage());
        }

        $this->assertSame(2, KnowledgeChunk::query()->where('source_id', 'second')->count());
    }
}
