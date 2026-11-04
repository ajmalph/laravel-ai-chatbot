<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Data;

final readonly class SourceReference
{
    public function __construct(
        public string $id,
        public string $source,
        public string $sourceId,
        public string $title,
        public string $url,
    ) {}

    /** @return array{id: string, source: string, source_id: string, title: string, url: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'source_id' => $this->sourceId,
            'title' => $this->title,
            'url' => $this->url,
        ];
    }
}
