<?php

declare(strict_types=1);
use Pgs\LaravelAiChatbot\Providers\OpenAiProvider;
use Pgs\LaravelAiChatbot\Retrieval\MySqlKnowledgeRetriever;

return [
    'enabled' => env('AI_CHATBOT_ENABLED', true),
    'provider' => env('AI_CHATBOT_PROVIDER', 'openai'),

    'providers' => [
        'openai' => [
            'driver' => OpenAiProvider::class,
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('AI_CHATBOT_MODEL', env('OPENAI_MODEL', 'gpt-5-mini')),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'timeout' => (int) env('AI_CHATBOT_OPENAI_TIMEOUT', 30),
            'attempts' => (int) env('AI_CHATBOT_OPENAI_ATTEMPTS', 2),
            'retry_delay_ms' => (int) env('AI_CHATBOT_OPENAI_RETRY_DELAY_MS', 250),
            'max_output_tokens' => (int) env('AI_CHATBOT_MAX_OUTPUT_TOKENS', 700),
            // Keep null by default so estimates never silently use stale provider pricing.
            'pricing' => [
                'default' => [
                    'input_per_million' => env('AI_CHATBOT_INPUT_COST_PER_MILLION'),
                    'output_per_million' => env('AI_CHATBOT_OUTPUT_COST_PER_MILLION'),
                ],
            ],
        ],
    ],

    'chat' => [
        'retrieval_limit' => (int) env('AI_CHATBOT_CHAT_RETRIEVAL_LIMIT', 5),
        'max_question_characters' => (int) env('AI_CHATBOT_MAX_QUESTION_CHARACTERS', 1000),
        'max_conversation_messages' => (int) env('AI_CHATBOT_MAX_CONVERSATION_MESSAGES', 10),
        'max_conversation_characters' => (int) env('AI_CHATBOT_MAX_CONVERSATION_CHARACTERS', 6000),
        'fallback' => [
            'en' => 'I do not have enough verified public information to answer that question.',
            'ar' => 'لا تتوفر لدي معلومات عامة موثوقة كافية للإجابة عن هذا السؤال.',
        ],
    ],

    'tools' => [
        'register_bundled' => env('AI_CHATBOT_REGISTER_BUNDLED_TOOLS', false),
        'max_registered_per_request' => (int) env('AI_CHATBOT_MAX_REGISTERED_TOOLS', 10),
        'max_calls' => (int) env('AI_CHATBOT_MAX_TOOL_CALLS', 4),
        'max_rounds' => (int) env('AI_CHATBOT_MAX_TOOL_ROUNDS', 3),
        'max_argument_characters' => (int) env('AI_CHATBOT_MAX_TOOL_ARGUMENT_CHARACTERS', 4000),
        'max_result_characters' => (int) env('AI_CHATBOT_MAX_TOOL_RESULT_CHARACTERS', 8000),
        'max_total_result_characters' => (int) env('AI_CHATBOT_MAX_TOTAL_TOOL_RESULT_CHARACTERS', 12000),
        'audit' => env('AI_CHATBOT_TOOL_AUDIT', true),
    ],

    'knowledge_sources' => [
        'register_bundled' => env('AI_CHATBOT_REGISTER_BUNDLED_SOURCES', true),
    ],

    'index' => [
        'locales' => ['en', 'ar'],
        'chunk_size' => (int) env('AI_CHATBOT_CHUNK_SIZE', 2000),
        'chunk_overlap' => (int) env('AI_CHATBOT_CHUNK_OVERLAP', 200),
        'source_batch_size' => (int) env('AI_CHATBOT_SOURCE_BATCH_SIZE', 100),
        'queue' => env('AI_CHATBOT_QUEUE', 'default'),
        'schedule' => [
            'enabled' => env('AI_CHATBOT_RECONCILE_ENABLED', false),
            'time' => env('AI_CHATBOT_RECONCILE_TIME', '02:00'),
        ],
    ],

    'retrieval' => [
        'driver' => MySqlKnowledgeRetriever::class,
        'max_results' => (int) env('AI_CHATBOT_MAX_RESULTS', 8),
        'fallback_candidate_limit' => (int) env('AI_CHATBOT_FALLBACK_CANDIDATES', 100),
        'fulltext_min_term_length' => (int) env('AI_CHATBOT_FULLTEXT_MIN_TERM_LENGTH', 3),
        'max_query_characters' => (int) env('AI_CHATBOT_MAX_QUERY_CHARACTERS', 500),
        'max_result_characters' => (int) env('AI_CHATBOT_MAX_RESULT_CHARACTERS', 2500),
        'max_context_characters' => (int) env('AI_CHATBOT_MAX_CONTEXT_CHARACTERS', 8000),
    ],

    'routes' => [
        'enabled' => env('AI_CHATBOT_ROUTES_ENABLED', false),
        'prefix' => env('AI_CHATBOT_ROUTE_PREFIX', 'api/ai-chatbot'),
        'middleware' => ['api'],
    ],

    'api' => [
        'authentication_optional' => env('AI_CHATBOT_AUTH_OPTIONAL', true),
        'default_locale' => env('AI_CHATBOT_DEFAULT_LOCALE', 'en'),
        'max_message_characters' => (int) env('AI_CHATBOT_API_MAX_MESSAGE_CHARACTERS', 1000),
        'max_history_messages' => (int) env('AI_CHATBOT_API_MAX_HISTORY_MESSAGES', 50),
        'conversation_ttl_hours' => (int) env('AI_CHATBOT_CONVERSATION_TTL_HOURS', 168),
        'rate_limit_per_minute' => (int) env('AI_CHATBOT_RATE_LIMIT_PER_MINUTE', 20),
        'daily_message_budget' => (int) env('AI_CHATBOT_DAILY_MESSAGE_BUDGET', 50),
        'daily_token_budget' => (int) env('AI_CHATBOT_DAILY_TOKEN_BUDGET', 50000),
        'cleanup' => [
            'enabled' => env('AI_CHATBOT_CLEANUP_ENABLED', true),
            'time' => env('AI_CHATBOT_CLEANUP_TIME', '03:00'),
            'limit' => (int) env('AI_CHATBOT_CLEANUP_LIMIT', 500),
        ],
    ],

    'widget' => [
        'locale' => env('AI_CHATBOT_WIDGET_LOCALE'),
        'title' => env('AI_CHATBOT_WIDGET_TITLE'),
        'screen_title' => [
            'en' => env('AI_CHATBOT_WIDGET_TITLE_EN', 'AI Assistant'),
            'ar' => env('AI_CHATBOT_WIDGET_TITLE_AR', 'المساعد الذكي'),
        ],
        'welcome' => [
            'en' => env('AI_CHATBOT_WIDGET_WELCOME_EN', 'Hello! How can I help you today?'),
            'ar' => env('AI_CHATBOT_WIDGET_WELCOME_AR', 'مرحباً! كيف يمكنني مساعدتك اليوم؟'),
        ],
        'fallback' => [
            'en' => env('AI_CHATBOT_WIDGET_FALLBACK_EN', 'Sorry, I could not complete that request. Please try again.'),
            'ar' => env('AI_CHATBOT_WIDGET_FALLBACK_AR', 'عذراً، تعذر إكمال طلبك. يرجى المحاولة مرة أخرى.'),
        ],
        'default_followups' => ['en' => [], 'ar' => []],
        'predefined_questions_enabled' => env('AI_CHATBOT_PREDEFINED_QUESTIONS_ENABLED', true),
        'assets' => [
            'css' => env('AI_CHATBOT_WIDGET_CSS_URL'),
            'js' => env('AI_CHATBOT_WIDGET_JS_URL'),
        ],
    ],

    'admin' => [
        'enabled' => env('AI_CHATBOT_ADMIN_ENABLED', false),
        'prefix' => env('AI_CHATBOT_ADMIN_PREFIX', 'admin/ai-chatbot'),
        'authentication_middleware' => [],
        'authorization_middleware' => [],
        'layout' => env('AI_CHATBOT_ADMIN_LAYOUT'),
        'retention_days' => (int) env('AI_CHATBOT_RETENTION_DAYS', 30),
    ],

    'predefined' => [
        'routes' => [
            'enabled' => env('AI_CHATBOT_PREDEFINED_API_ENABLED', true),
            'prefix' => 'api/v1/chatbot',
            'middleware' => ['api', 'throttle:60,1'],
        ],
        'cms_page_types' => [],
    ],

    'tables' => [
        'conversations' => 'ai_chatbot_conversations',
        'messages' => 'ai_chatbot_messages',
        'knowledge_chunks' => 'ai_chatbot_knowledge_chunks',
        'settings' => 'ai_chatbot_settings',
        'tool_logs' => 'ai_chatbot_tool_logs',
    ],
];
