<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tests\Unit;

use Pgs\LaravelAiChatbot\Tests\Fixtures\ArrayKnowledgeSource;
use Pgs\LaravelAiChatbot\Tests\TestCase;

final class KnowledgeSourceContractTest extends TestCase
{
    public function test_extraction_maps_both_languages_and_stable_identity(): void
    {
        $items = iterator_to_array((new ArrayKnowledgeSource)->iterate(chunkSize: 1));

        $this->assertCount(1, $items);
        $this->assertSame('published', $items[0]->id);
        $this->assertSame('English title', $items[0]->title('en'));
        $this->assertSame('العنوان العربي', $items[0]->title('ar'));
        $this->assertSame('English body', $items[0]->body('en'));
        $this->assertSame('النص العربي', $items[0]->body('ar'));
        $this->assertSame('/ar/content/published', $items[0]->url('ar'));
    }

    public function test_iteration_and_individual_lookup_never_expose_private_content(): void
    {
        $source = new ArrayKnowledgeSource;

        $this->assertNull($source->find('draft'));
        $this->assertNull($source->find('missing'));
        $this->assertTrue($source->find('published')->isPublic);
        $this->assertSame(['published'], array_map(
            static fn ($item): string => $item->id,
            iterator_to_array($source->iterate()),
        ));
    }

    public function test_individual_lookup_revalidates_visibility(): void
    {
        $source = new ArrayKnowledgeSource;
        $this->assertNotNull($source->find('published'));

        $source->records['published']['public'] = false;

        $this->assertNull($source->find('published'));
    }
}
