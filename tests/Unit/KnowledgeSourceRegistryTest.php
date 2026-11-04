<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tests\Unit;

use InvalidArgumentException;
use Pgs\LaravelAiChatbot\Facades\AiChatbot;
use Pgs\LaravelAiChatbot\Tests\Fixtures\ArrayKnowledgeSource;
use Pgs\LaravelAiChatbot\Tests\TestCase;

final class KnowledgeSourceRegistryTest extends TestCase
{
    public function test_sources_can_be_registered_and_resolved_through_the_facade(): void
    {
        AiChatbot::registerKnowledgeSource('pages', ArrayKnowledgeSource::class);

        $this->assertTrue(AiChatbot::knowledgeSources()->has('pages'));
        $this->assertSame(['pages'], AiChatbot::knowledgeSources()->names());
        $this->assertInstanceOf(ArrayKnowledgeSource::class, AiChatbot::knowledgeSource('pages'));
    }

    public function test_duplicate_and_invalid_registrations_are_rejected(): void
    {
        AiChatbot::registerKnowledgeSource('pages', ArrayKnowledgeSource::class);

        $this->expectException(InvalidArgumentException::class);
        AiChatbot::registerKnowledgeSource('pages', ArrayKnowledgeSource::class);
    }

    public function test_invalid_source_names_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AiChatbot::registerKnowledgeSource('Invalid Name', ArrayKnowledgeSource::class);
    }
}
