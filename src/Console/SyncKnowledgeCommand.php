<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Pgs\LaravelAiChatbot\Jobs\SyncKnowledgeSourceJob;
use Pgs\LaravelAiChatbot\Knowledge\KnowledgeSourceRegistry;
use Pgs\LaravelAiChatbot\Services\KnowledgeIndexService;

final class SyncKnowledgeCommand extends Command
{
    protected $signature = 'ai-chatbot:sync
        {--source= : Synchronize one registered source}
        {--locale= : Synchronize one configured locale}
        {--fresh : Delete the selected index scope before rebuilding it}
        {--queue : Dispatch one background job per source}
        {--force : Skip destructive-operation confirmation}';

    protected $description = 'Synchronize public CMS content into the AI chatbot knowledge index';

    public function handle(KnowledgeIndexService $index, KnowledgeSourceRegistry $sources): int
    {
        $source = $this->option('source');
        $locale = $this->option('locale');

        if (is_string($source) && ! $sources->has($source)) {
            $this->components->error("Knowledge source [{$source}] is not registered.");

            return self::INVALID;
        }

        $supportedLocales = config('ai-chatbot.index.locales', ['en', 'ar']);
        if (is_string($locale) && ! in_array($locale, $supportedLocales, true)) {
            $this->components->error("Locale [{$locale}] is not configured for indexing.");

            return self::INVALID;
        }

        if ($this->option('fresh') && ! $this->option('force')) {
            if (! $this->confirm('This will delete the selected knowledge index scope before rebuilding it. Continue?')) {
                $this->components->warn('Fresh synchronization cancelled.');

                return self::FAILURE;
            }
        }

        $names = is_string($source) ? [$source] : $sources->names();

        if ($this->option('queue')) {
            foreach ($names as $name) {
                SyncKnowledgeSourceJob::dispatch($name, is_string($locale) ? $locale : null, (bool) $this->option('fresh'));
            }

            $this->components->info(count($names).' knowledge synchronization job(s) dispatched.');

            return self::SUCCESS;
        }

        try {
            $result = $index->sync(
                is_string($source) ? $source : null,
                is_string($locale) ? $locale : null,
                (bool) $this->option('fresh'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        $this->components->info(sprintf(
            'Synchronized %d source(s), %d document(s), and %d chunk(s); removed %d stale chunk(s).',
            $result['sources'],
            $result['documents'],
            $result['chunks'],
            $result['deleted'],
        ));

        return self::SUCCESS;
    }
}
