<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Pgs\LaravelAiChatbot\Services\KnowledgeIndexService;

final class SyncKnowledgeSourceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly string $source,
        public readonly ?string $locale = null,
        public readonly bool $fresh = false,
    ) {
        $this->onQueue((string) config('ai-chatbot.index.queue', 'default'));
    }

    public function uniqueId(): string
    {
        return implode(':', [$this->source, $this->locale ?? 'all', $this->fresh ? 'fresh' : 'incremental']);
    }

    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function handle(KnowledgeIndexService $index): void
    {
        // Exceptions intentionally bubble so Laravel retries and records exhausted jobs.
        $index->syncSource($this->source, $this->locale, $this->fresh);
    }
}
