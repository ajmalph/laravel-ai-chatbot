<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tests\Unit;

use Pgs\LaravelAiChatbot\Retrieval\RetrievalResult;
use Pgs\LaravelAiChatbot\Services\PromptBuilder;
use Pgs\LaravelAiChatbot\Tests\TestCase;

final class PromptBuilderTest extends TestCase
{
    public function test_it_preserves_arabic_and_marks_knowledge_as_untrusted(): void
    {
        $prompt = (new PromptBuilder)->build('ما هي المبادرة؟', 'ar', [
            new RetrievalResult('initiatives', '7', 'ar', 'مبادرة', 'محتوى عربي', 'https://example.test/ar/7', 1.0),
        ]);

        self::assertStringContainsString('Answer in Arabic', $prompt['instructions']);
        self::assertStringContainsString('untrusted reference data', $prompt['instructions']);
        self::assertStringContainsString('محتوى عربي', $prompt['prompt']);
        self::assertStringContainsString('"reference":"S1"', $prompt['prompt']);
    }
}
