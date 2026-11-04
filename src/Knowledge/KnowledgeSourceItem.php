<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Knowledge;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class KnowledgeSourceItem
{
    public DateTimeImmutable $modifiedAt;

    /**
     * @param  array{en: string, ar: string}  $titles
     * @param  array{en: string, ar: string}  $bodies
     * @param  array{en: string, ar: string}  $urls
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public bool $isPublic,
        public array $titles,
        public array $bodies,
        public array $urls,
        DateTimeInterface $modifiedAt,
        public array $metadata = [],
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('A knowledge source item ID cannot be empty.');
        }

        foreach (['titles' => $titles, 'bodies' => $bodies, 'urls' => $urls] as $field => $values) {
            if (array_keys($values) !== ['en', 'ar']) {
                throw new InvalidArgumentException("Knowledge source {$field} must contain exactly the en and ar keys.");
            }
        }

        $this->modifiedAt = DateTimeImmutable::createFromInterface($modifiedAt);
    }

    public function title(string $locale): string
    {
        return $this->localized($this->titles, $locale);
    }

    public function body(string $locale): string
    {
        return $this->localized($this->bodies, $locale);
    }

    public function url(string $locale): string
    {
        return $this->localized($this->urls, $locale);
    }

    /** @param array{en: string, ar: string} $values */
    private function localized(array $values, string $locale): string
    {
        if (! array_key_exists($locale, $values)) {
            throw new InvalidArgumentException("Unsupported knowledge source locale [{$locale}].");
        }

        return $values[$locale];
    }
}
