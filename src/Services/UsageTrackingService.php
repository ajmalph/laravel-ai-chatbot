<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Services;

use Pgs\LaravelAiChatbot\Data\ProviderResponse;
use Pgs\LaravelAiChatbot\Data\Usage;

final class UsageTrackingService
{
    public function fromProvider(ProviderResponse $response): Usage
    {
        $pricing = config("ai-chatbot.providers.openai.pricing.{$response->model}")
            ?? config('ai-chatbot.providers.openai.pricing.default');

        $cost = null;
        if (is_array($pricing) && is_numeric($pricing['input_per_million'] ?? null) && is_numeric($pricing['output_per_million'] ?? null)) {
            $cost = round(
                (($response->inputTokens / 1_000_000) * (float) $pricing['input_per_million'])
                + (($response->outputTokens / 1_000_000) * (float) $pricing['output_per_million']),
                8,
            );
        }

        return new Usage(
            inputTokens: $response->inputTokens,
            outputTokens: $response->outputTokens,
            totalTokens: $response->inputTokens + $response->outputTokens,
            estimatedCostUsd: $cost,
        );
    }
}
