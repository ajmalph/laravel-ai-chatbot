<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tools;

use Carbon\CarbonImmutable;
use Pgs\LaravelAiChatbot\Contracts\ToolInterface;
use Pgs\LaravelAiChatbot\Knowledge\KnowledgeSourceRegistry;
use Throwable;

final class UpcomingEventsTool implements ToolInterface
{
    public function __construct(private readonly KnowledgeSourceRegistry $sources) {}

    public function name(): string
    {
        return 'list_upcoming_events';
    }

    public function description(): string
    {
        return 'List published public events that have not yet occurred.';
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
        if (! $this->sources->has('events')) {
            return [];
        }

        $locale = $arguments['locale'];
        $limit = max(1, min(10, $arguments['limit']));
        $events = [];

        foreach ($this->sources->get('events')->iterate(50) as $item) {
            $rawDate = trim((string) ($item->metadata['event_date'] ?? ''));
            if ($rawDate === '') {
                continue;
            }

            try {
                $date = CarbonImmutable::parse($rawDate);
            } catch (Throwable) {
                continue;
            }

            if (! $item->isPublic || $date->isPast()) {
                continue;
            }

            $events[] = ['title' => $item->title($locale), 'date' => $date->toIso8601String(), 'url' => $item->url($locale)];
        }

        usort($events, fn (array $first, array $second): int => $first['date'] <=> $second['date']);

        return array_slice($events, 0, $limit);
    }

    public function maximumResultCharacters(): int
    {
        return 5000;
    }
}
