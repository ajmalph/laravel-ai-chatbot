<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tools;

use Pgs\LaravelAiChatbot\Contracts\ToolInterface;
use Pgs\LaravelAiChatbot\Knowledge\KnowledgeSourceRegistry;

final class PublicInitiativesTool implements ToolInterface
{
    public function __construct(private readonly KnowledgeSourceRegistry $sources) {}

    public function name(): string
    {
        return 'list_public_initiatives';
    }

    public function description(): string
    {
        return 'List published public initiatives from the registered initiatives adapter.';
    }

    public function argumentsSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'locale' => ['type' => 'string', 'enum' => ['en', 'ar']],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
            ],
            'required' => ['locale', 'limit'],
            'additionalProperties' => false,
        ];
    }

    public function authorizationRequirements(): array
    {
        return ['public' => true];
    }

    public function executeReadOnly(array $arguments): array
    {
        if (! $this->sources->has('initiatives')) {
            return [];
        }

        $locale = $arguments['locale'];
        $limit = max(1, min(10, $arguments['limit']));
        $initiatives = [];

        foreach ($this->sources->get('initiatives')->iterate(50) as $item) {
            if (! $item->isPublic) {
                continue;
            }

            $initiatives[] = [
                'title' => $item->title($locale),
                'summary' => mb_substr($item->body($locale), 0, 500),
                'url' => $item->url($locale),
            ];

            if (count($initiatives) >= $limit) {
                break;
            }
        }

        return $initiatives;
    }

    public function maximumResultCharacters(): int
    {
        return 8000;
    }
}
