<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Admin;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Schema;

final class SettingsService
{
    private const MAP = [
        'enabled' => 'ai-chatbot.enabled',
        'screen_title_en' => 'ai-chatbot.widget.screen_title.en',
        'screen_title_ar' => 'ai-chatbot.widget.screen_title.ar',
        'welcome_en' => 'ai-chatbot.widget.welcome.en',
        'welcome_ar' => 'ai-chatbot.widget.welcome.ar',
        'fallback_en' => 'ai-chatbot.widget.fallback.en',
        'fallback_ar' => 'ai-chatbot.widget.fallback.ar',
        'default_followups_en' => 'ai-chatbot.widget.default_followups.en',
        'default_followups_ar' => 'ai-chatbot.widget.default_followups.ar',
        'predefined_questions_enabled' => 'ai-chatbot.widget.predefined_questions_enabled',
        'retrieval_limit' => 'ai-chatbot.chat.retrieval_limit',
        'retention_days' => 'ai-chatbot.admin.retention_days',
        'daily_message_budget' => 'ai-chatbot.api.daily_message_budget',
        'daily_token_budget' => 'ai-chatbot.api.daily_token_budget',
    ];

    public function __construct(private readonly ConnectionInterface $database, private readonly Repository $config) {}

    public function all(): array
    {
        $values = [];
        foreach (self::MAP as $key => $path) {
            $values[$key] = $this->config->get($path);
        }

        return $values;
    }

    public function update(array $values): void
    {
        foreach (self::MAP as $key => $path) {
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $value = $values[$key];
            $this->database->table(config('ai-chatbot.tables.settings'))->updateOrInsert(
                ['scope_type' => 'global', 'scope_id' => 'global', 'key' => $key],
                ['value' => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'is_encrypted' => false, 'created_at' => now(), 'updated_at' => now()],
            );
            $this->config->set($path, $value);
        }
    }

    public function apply(): void
    {
        $table = config('ai-chatbot.tables.settings');
        if (! Schema::hasTable($table)) {
            return;
        }
        foreach ($this->database->table($table)->where('scope_type', 'global')->where('scope_id', 'global')->whereIn('key', array_keys(self::MAP))->get(['key', 'value']) as $setting) {
            $this->config->set(self::MAP[$setting->key], json_decode($setting->value, true));
        }
    }
}
