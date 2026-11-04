<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Console;

use Illuminate\Console\Command;
use Pgs\LaravelAiChatbot\Models\Conversation;

final class CleanupConversationsCommand extends Command
{
    protected $signature = 'ai-chatbot:cleanup {--limit=500 : Maximum expired conversations to prune}';

    protected $description = 'Soft-delete expired AI chatbot conversations in bounded batches';

    public function handle(): int
    {
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $count = 0;
        Conversation::query()->where('expires_at', '<=', now())->oldest('expires_at')->limit($limit)
            ->get(['id'])->each(function (Conversation $conversation) use (&$count): void {
                $conversation->update(['status' => 'expired']);
                $conversation->delete();
                $count++;
            });
        $this->info("Expired conversations removed: {$count}");

        return self::SUCCESS;
    }
}
