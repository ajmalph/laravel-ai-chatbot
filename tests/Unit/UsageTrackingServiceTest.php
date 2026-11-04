<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tests\Unit;

use Pgs\LaravelAiChatbot\Data\ProviderResponse;
use Pgs\LaravelAiChatbot\Services\UsageTrackingService;
use Pgs\LaravelAiChatbot\Tests\TestCase;

final class UsageTrackingServiceTest extends TestCase
{
    public function test_it_tracks_tokens_and_configured_estimated_cost(): void
    {
        config()->set('ai-chatbot.providers.openai.pricing.gpt-test', [
            'input_per_million' => 2,
            'output_per_million' => 8,
        ]);

        $usage = (new UsageTrackingService)->fromProvider(
            new ProviderResponse('Answer', 1_000_000, 500_000, 'gpt-test'),
        );

        self::assertSame(1_500_000, $usage->totalTokens);
        self::assertSame(6.0, $usage->estimatedCostUsd);
    }

    public function test_it_omits_estimates_when_pricing_is_not_configured(): void
    {
        $usage = (new UsageTrackingService)->fromProvider(
            new ProviderResponse('Answer', 10, 5, 'unknown-model'),
        );

        self::assertNull($usage->estimatedCostUsd);
    }
}
