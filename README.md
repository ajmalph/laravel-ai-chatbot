# PGS Laravel AI Chatbot

Reusable, CMS-agnostic foundations for adding an AI chatbot to Laravel 13 applications.

See the [step-by-step API testing guide](docs/API_TESTING.md) for environment setup, curl/Postman requests, credentials, idempotency, and expected errors.

Phases 1-9 provide package discovery, CMS-independent knowledge adapters, indexing, bounded retrieval, grounded Responses API generation, secure function calling, configurable conversation APIs, an accessible Blade widget, and an optional administration module.

## Optional administration module

The administration routes are disabled by default. Publish the configuration, define application-specific authorization abilities, and explicitly supply both authentication and authorization middleware before enabling them:

```php
// config/ai-chatbot.php
'admin' => [
    'enabled' => env('AI_CHATBOT_ADMIN_ENABLED', false),
    'prefix' => 'admin/ai-chatbot',
    'authentication_middleware' => ['web', 'auth'],
    'authorization_middleware' => [\App\Http\Middleware\EnsureUserIsAdministrator::class],
    'layout' => null, // Or a host Blade layout such as 'admin.layouts.master'.
    'retention_days' => 30,
],
```

Define all four abilities in the host application's authorization provider. The package checks the relevant ability again inside every controller action, independently of route middleware:

```php
Gate::define('ai-chatbot.admin.view', fn (User $user) => $user->isAdministrator());
Gate::define('ai-chatbot.admin.settings', fn (User $user) => $user->isAdministrator());
Gate::define('ai-chatbot.admin.sync', fn (User $user) => $user->isAdministrator());
Gate::define('ai-chatbot.admin.delete', fn (User $user) => $user->isAdministrator());
```

Then set `AI_CHATBOT_ADMIN_ENABLED=true`, publish assets with `php artisan vendor:publish --tag=ai-chatbot-assets`, and visit the configured prefix. Enabling the module without both middleware groups fails closed during application boot. The settings UI uses a fixed allowlist and never reads or renders provider credentials. Conversation deletion is refused until the configured retention age is reached.

### Predefined questions CMS

The administration module includes category and predefined-question management under its configured admin prefix. Add the following abilities to the host permission system and map them through Laravel's Gate (Spatie permissions work through the Gate automatically when permissions use these names):

```text
ai-chatbot.admin.questions.view
ai-chatbot.admin.questions.manage
```

For this CMS, publish the package configuration and explicitly allow its page model for the optional polymorphic page association:

```php
'predefined' => [
    'routes' => [
        'enabled' => true,
        'prefix' => 'api/v1/chatbot',
        'middleware' => ['api', 'throttle:60,1'],
    ],
    'cms_page_types' => [\App\Models\Admodels\PostModel::class],
],
```

Apply the package migration and seed navigation categories once:

```bash
php artisan migrate
php artisan db:seed --class="Pgs\LaravelAiChatbot\Database\Seeders\PredefinedQuestionCategorySeeder"
```

The seeder uses stable slugs with `firstOrCreate`, so rerunning it adds missing defaults without overwriting administrator changes. It seeds navigation categories only—no claims or strategy answers.

Public, read-only endpoints are localized with `?locale=en` or `?locale=ar`:

```text
GET /api/v1/chatbot/config
GET /api/v1/chatbot/categories
GET /api/v1/chatbot/categories/{id}/questions
GET /api/v1/chatbot/questions/{id}
```

Only active questions belonging to active categories are returned. Follow-ups are one-level references (`id` and localized question), so even mutually linked questions cannot produce recursive API responses. Answers are stored as plain text and should contain only content approved by the site's administrators.

## Chat widget

Enable the API and publish the lightweight CSS and vanilla JavaScript:

```bash
php artisan vendor:publish --tag=ai-chatbot-assets
php artisan config:clear
```

Place the component once in the host layout, normally before `</body>`:

```blade
<x-ai-chatbot::widget />
```

It accepts `locale`, `title`, `welcome`, `fallback`, and `api-url` props. English and Arabic, including RTL layout, are built in. Conversation state is retained for the current browser tab with `sessionStorage`; visitor credentials are sent only through `X-AI-Chatbot-Token` and never placed in URLs.

Publish optional overrides with:

```bash
php artisan vendor:publish --tag=ai-chatbot-views
php artisan vendor:publish --tag=ai-chatbot-translations
```

Configure global defaults through `ai-chatbot.widget` or the corresponding `AI_CHATBOT_WIDGET_*` environment values. The widget renders messages with DOM `textContent`, accepts only HTTP(S) source links, and does not require React, Vue, Tailwind, Bootstrap, or a host build pipeline.

## Requirements

- PHP 8.3+
- Laravel 13

## Local installation

Add a Composer path repository to the host project:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/laravel-ai-chatbot",
            "options": { "symlink": true }
        }
    ]
}
```

Then install the package:

```bash
composer require pgs/laravel-ai-chatbot:@dev
```

Laravel discovers `AiChatbotServiceProvider` and the optional `AiChatbot` facade automatically.

## Publishing

```bash
php artisan vendor:publish --tag=ai-chatbot-config
php artisan vendor:publish --tag=ai-chatbot-migrations
```

Package migrations load automatically. Publishing them is only needed when the host application needs to customize its schema.

## Configuration

All provider, prompt-limit, table, and route settings live in `config/ai-chatbot.php`. Routes remain disabled by default and there are no public chat endpoints yet.

```php
use Pgs\LaravelAiChatbot\Facades\AiChatbot;

$provider = AiChatbot::config('provider');
```

## Registering knowledge sources

Implement `KnowledgeSourceInterface` for each CMS model and register each known adapter explicitly from a host service provider. Adapters may live in the package or host application; host models remain outside this reusable package:

```php
use App\KnowledgeSources\PageKnowledgeSource;
use Pgs\LaravelAiChatbot\Facades\AiChatbot;

public function boot(): void
{
    AiChatbot::registerKnowledgeSource('pages', PageKnowledgeSource::class);
}
```

An adapter yields `KnowledgeSourceItem` objects from `iterate()` and performs a fresh public-visibility query in `find()`. Both methods must exclude drafts, private records, soft-deleted records, and anything else that is not publicly accessible. The item contains a stable source ID, explicit visibility, English and Arabic titles/bodies/URLs, and a modification timestamp.

Class names are resolved through Laravel's container, so adapters may use constructor injection. Registration does not install observers or inspect model names. See [Knowledge source adapters](docs/knowledge-sources.md) for a complete example.

This repository's five `PostModel` adapters are bundled under `Pgs\LaravelAiChatbot\KnowledgeSources` and registered by the package provider. Set `AI_CHATBOT_REGISTER_BUNDLED_SOURCES=false` in another application that does not use this CMS, then register that application's adapters as shown above.

See [Architecture](docs/architecture.md) for boundaries and the AI integration decision.

## Synchronizing the knowledge index

```bash
php artisan ai-chatbot:sync
php artisan ai-chatbot:sync --source=pages
php artisan ai-chatbot:sync --locale=ar
php artisan ai-chatbot:sync --queue
php artisan ai-chatbot:sync --fresh
```

`--fresh` deletes the selected index scope before rebuilding and therefore asks for confirmation. Use `--force` only in controlled, non-interactive deployments. Normal synchronization performs idempotent upserts and removes stale chunks after a source has completed successfully.

Enable scheduled queued reconciliation with:

```env
AI_CHATBOT_RECONCILE_ENABLED=true
AI_CHATBOT_RECONCILE_TIME=02:00
```

Laravel's scheduler and a queue worker must be running. Source exceptions bubble to Laravel so normal retry and failed-job handling apply.

## Retrieving knowledge

The default version 1 driver uses MySQL FULLTEXT for suitable terms and a bounded lexical fallback for short terms or databases without FULLTEXT support:

```php
use Pgs\LaravelAiChatbot\Facades\AiChatbot;
use Pgs\LaravelAiChatbot\Retrieval\ContextBuilder;

$results = AiChatbot::retrieve(
    query: 'climate strategy',
    locale: 'en',
    sources: ['pages', 'publications'],
    limit: 5,
);

$context = app(ContextBuilder::class)->build($results);
```

Results include the public title, bounded content, canonical URL, source identity, locale, score, and metadata. Source visibility is revalidated before results are returned. Configure a future vector or Qdrant implementation by binding or configuring another class that implements `KnowledgeRetrieverInterface`; no vector service is required by this package.

## Asking grounded questions

Set credentials only in the server environment:

```env
OPENAI_API_KEY=
AI_CHATBOT_MODEL=gpt-5-mini
AI_CHATBOT_MAX_OUTPUT_TOKENS=700
AI_CHATBOT_OPENAI_TIMEOUT=30
AI_CHATBOT_OPENAI_ATTEMPTS=2
```

Never expose `OPENAI_API_KEY` through frontend configuration. Then ask through the facade:

```php
$response = AiChatbot::ask(
    question: 'What services are available?',
    locale: 'en',
    conversation: [
        ['role' => 'user', 'content' => 'Tell me about public services.'],
        ['role' => 'assistant', 'content' => 'Which service area?'],
    ],
    sources: ['pages', 'faqs'],
);

$response->answer;
$response->sources; // Verified references reconstructed from retrieved CMS records.
$response->usage->totalTokens;
$response->usage->estimatedCostUsd;
```

The model receives only bounded retrieved chunks and bounded conversation history, never the CMS database. Empty retrieval with no registered tools returns a localized deterministic fallback without calling OpenAI. URLs returned in `sources` come solely from revalidated adapters; model-generated URLs are never promoted to references.

## Registering read-only tools

Tools are opt-in classes implementing `ToolInterface`. They expose a strict JSON Schema, explicit authorization policy, read-only handler, and result limit:

```php
use App\Ai\Tools\UpcomingEventsTool;
use Pgs\LaravelAiChatbot\Facades\AiChatbot;

AiChatbot::registerTool('list_upcoming_events', UpcomingEventsTool::class);
```

Every object property must be required and schemas must set `additionalProperties` to `false`. Public tools must explicitly return `['public' => true]`; protected tools return `['abilities' => ['ability-name']]` and require an authenticated actor passed to `AiChatbot::ask(..., actor: $user)`.

The package validates and authorizes every call, applies call/round/argument/result limits, strips markup, and stores a redacted audit record containing argument names but not values or results. Only registered PHP services can run. SQL, PHP, shell, OpenAI-hosted tools, and unrestricted HTTP capabilities are never exposed.

Bundled examples use the explicitly registered `events` and `initiatives` knowledge adapters. Disable them with `AI_CHATBOT_REGISTER_BUNDLED_TOOLS=false` when using different host adapters. Limits are configurable through `AI_CHATBOT_MAX_TOOL_CALLS`, `AI_CHATBOT_MAX_TOOL_ROUNDS`, and the related options in `config/ai-chatbot.php`.

Cost estimates are disabled until current prices are configured, avoiding stale assumptions:

```env
AI_CHATBOT_INPUT_COST_PER_MILLION=0.00
AI_CHATBOT_OUTPUT_COST_PER_MILLION=0.00
```

Use the current prices for the configured model. Token counts always come from the provider response even when price configuration is absent.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Tests use an in-memory SQLite database and mocked Responses API payloads, prevent stray HTTP requests, and never migrate the host application's database or make paid API calls.
