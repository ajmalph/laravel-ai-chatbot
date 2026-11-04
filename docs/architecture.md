# Architecture

## Phase 1 boundary

The package owns its configuration, migrations, route group, service-provider integration, and public manager/facade entry point. It does not depend on host models, authentication classes, CMS tables, or presentation conventions. The polymorphic `participant` columns and string-based knowledge source identifiers keep host integration optional.

The five package-owned tables cover:

- conversations and their optional host participant;
- ordered chat messages and provider usage metadata;
- provider-neutral knowledge chunks (content only in Phase 1);
- scoped package settings;
- auditable tool executions linked to a conversation or message.

Table names are configurable. Portable JSON/text columns are used rather than a database-specific vector type. Embeddings and retrieval are deliberately deferred to the RAG phase.

## AI integration decision

**Decision: target Laravel's first-party AI SDK behind a package-owned adapter in the implementation phase; do not couple the domain or schema directly to it yet.**

Laravel AI SDK is preferred over a hand-written OpenAI Responses API client because it is first-party, Laravel-native, supports OpenAI's Responses endpoint, and supplies provider abstraction, streaming, tools, structured output, embeddings, usage reporting, failover, and testing fakes. Those capabilities match the package's likely later phases while avoiding an OpenAI-only public API.

A direct Responses API integration would provide immediate access to every OpenAI-specific field and reduce one abstraction layer. Its cost is package-owned HTTP authentication, payload/stream parsing, retries, error normalization, test doubles, and tighter provider lock-in. It remains a possible internal adapter if a future requirement is missing from the SDK.

No `laravel/ai`, OpenAI client, API key, or network call is included in Phase 1. Before implementing AI calls, pin a Laravel AI SDK version compatible with Laravel 13 and define package-owned contracts so consumers are insulated from SDK changes.

References:

- Laravel 13 AI SDK documentation: https://laravel.com/framework/docs/13.x/ai-sdk
- OpenAI Responses API documentation: https://platform.openai.com/docs/api-reference/responses

## Service container

`AiChatbotServiceProvider` merges configuration, registers `AiChatbotManager` as a singleton under both its class and `ai-chatbot`, loads package migrations, and conditionally delegates route loading to `RouteRegistrar`. Laravel Composer metadata provides provider and facade auto-discovery.

## Future extension points

Later phases should add contracts and adapters for chat generation, embeddings, retrieval, tools, persistence, and host participant resolution. Controllers and UI should consume those contracts rather than provider SDK types.

## Phase 2 knowledge boundary

`KnowledgeSourceRegistry` stores explicitly named, container-resolved implementations of `KnowledgeSourceInterface`. It does not scan models or attach observers. `KnowledgeSourceItem` is the package-owned transport object, keeping CMS models and SDK types out of downstream indexing code. Host adapters own publication rules, field mapping, canonical URLs, and cursor/chunk iteration. See [knowledge source adapters](knowledge-sources.md).

## Phase 3 indexing

`KnowledgeIndexService` sanitizes source text, preserves explicit English and Arabic records, creates overlapping Unicode-safe chunks, hashes their searchable fields, and upserts them using source, record, locale, and chunk index as the natural key. A per-run synchronization token enables reconciliation without holding every source ID in memory.

Full-source stale deletion runs only after the adapter iterator completes. If an adapter or queued job fails, the exception is left for Laravel's retry and failed-job infrastructure and unseen existing chunks are retained. Single-record synchronization and `chunksFor()` re-query the adapter; missing, deleted, draft, or private records are immediately removed before indexed content can be returned.

Scheduled reconciliation is opt-in and dispatches one unique queued job per registered source. `--fresh` is explicitly destructive and confirmation-gated.

## Phase 4 retrieval

`KnowledgeRetrieverInterface` is the stable driver boundary. Version 1 binds it to `MySqlKnowledgeRetriever`; a future Qdrant or vector implementation can replace that binding without changing callers or indexed source adapters.

MySQL FULLTEXT is used for queries containing terms that meet the configured minimum length. Short terms, unsupported database drivers, and FULLTEXT query failures use a recent candidate set capped by `fallback_candidate_limit` and constrained by indexed source and locale columns. The fallback never issues an unrestricted content `LIKE` scan.

Candidates are ranked with Unicode-aware English and Arabic lexical scoring. Overlapping chunks are deduplicated to the highest-scoring chunk per source record. Before output, the originating adapter is queried again and the record is refreshed through `KnowledgeIndexService`; missing or non-public records are deleted from every locale and omitted. `ContextBuilder` then applies a final hard character budget while retaining titles and canonical source URLs.

## Phase 5 grounded generation

Phase 5 follows the Phase 1 decision: `AiProviderInterface` is the package boundary and `OpenAiProvider` adapts Laravel AI SDK v1 to it. Laravel AI sends OpenAI text generation through the Responses API. The public `ChatbotService` and response DTO contain no Laravel AI or OpenAI SDK types, so another provider driver can replace it later.

The request pipeline is retrieval-first. `ChatbotService` obtains already revalidated public results, returns a deterministic localized fallback when none exist, and sends only a bounded set of chunks plus bounded `user`/`assistant` history. `PromptBuilder` serializes source material as untrusted JSON data and explicitly prevents instructions embedded in CMS content from overriding the system policy. Function tools are not registered.

Source references are generated after completion from retrieval DTOs, not from model citations. Consequently an answer cannot introduce a canonical URL into the structured `sources` list. Provider errors are mapped to stable safe codes; raw response bodies, prompts, API keys, and conversation content are not logged. Requests are stateless (`store=false`), use a configured total timeout and bounded transient retries, and reject empty responses.

Provider usage is mapped to the package's `Usage` DTO. Cost estimates require explicit per-million-token pricing in package configuration; without it, token counts remain available and estimated cost is `null` rather than relying on potentially stale prices.

## Phase 6 read-only tools

`ToolInterface` and `ToolRegistry` form the application boundary. Registration is explicit; the package neither scans for tools nor accepts executable code from the model. Tool schemas are checked for strict Responses API compatibility before registration. `LaravelAiToolAdapter` is the only SDK-facing layer and exposes registered tools to Laravel AI's bounded multi-step Responses loop.

Each invocation passes through `ToolExecutor`. It counts attempted calls before validation, rejects unknown tools and oversized or schema-invalid arguments, checks explicit public access or every declared Gate ability, calls only `executeReadOnly()`, sanitizes and bounds the result, then returns the SDK output associated with the provider's original `call_id`. Laravel AI preserves `function_call` items and sends matching `function_call_output` items until final text or the configured step ceiling.

Tool audit rows deliberately omit result content and argument values. They contain the tool name, normalized status/error code, duration, and argument key names. Provider errors and execution exceptions never place raw arguments, exception messages, prompts, credentials, or response bodies in audit storage.

The bundled public event and initiative examples read through registered knowledge adapters, which retain responsibility for public/published filtering and canonical URLs. No generic SQL, PHP, shell, web request, provider-hosted tool, or arbitrary callable is available.
