<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Providers;

use Illuminate\Contracts\Config\Repository;
use Laravel\Ai\Exceptions\FailoverableException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Messages\Message;
use Pgs\LaravelAiChatbot\Contracts\AiProviderInterface;
use Pgs\LaravelAiChatbot\Data\ProviderResponse;
use Pgs\LaravelAiChatbot\Exceptions\AiProviderException;
use Pgs\LaravelAiChatbot\Tools\ToolExecutionBudget;
use Pgs\LaravelAiChatbot\Tools\ToolExecutor;
use Throwable;

final class OpenAiProvider implements AiProviderInterface
{
    public function __construct(
        private readonly Repository $config,
        private readonly ToolExecutor $toolExecutor,
    ) {}

    public function generate(string $instructions, string $prompt, array $conversation = [], array $tools = [], mixed $actor = null): ProviderResponse
    {
        $key = trim((string) $this->config->get('ai-chatbot.providers.openai.api_key'));
        $model = trim((string) $this->config->get('ai-chatbot.providers.openai.model'));

        if ($key === '' || $model === '') {
            throw new AiProviderException('provider_not_configured');
        }

        // Laravel AI reads credentials from its provider configuration. The key never enters a prompt or log context.
        $this->config->set('ai.providers.openai.key', $key);
        $this->config->set('ai.providers.openai.url', $this->config->get('ai-chatbot.providers.openai.url'));
        $this->config->set('ai.providers.openai.store', false);

        $messages = array_map(
            fn (array $message): Message => new Message($message['role'], $message['content']),
            $conversation,
        );

        $budget = new ToolExecutionBudget(
            max(0, min(20, (int) $this->config->get('ai-chatbot.tools.max_calls', 4))),
            max(0, min(50_000, (int) $this->config->get('ai-chatbot.tools.max_total_result_characters', 12000))),
        );
        $toolAdapters = array_map(
            fn ($tool): LaravelAiToolAdapter => new LaravelAiToolAdapter($tool, $this->toolExecutor, $budget, $actor),
            $tools,
        );

        $agent = new OpenAiChatAgent(
            $instructions,
            $messages,
            max(1, min(8192, (int) $this->config->get('ai-chatbot.providers.openai.max_output_tokens', 700))),
            $toolAdapters,
            $tools === [] ? 1 : max(2, min(6, (int) $this->config->get('ai-chatbot.tools.max_rounds', 3) + 1)),
        );

        $attempts = max(1, min(3, (int) $this->config->get('ai-chatbot.providers.openai.attempts', 2)));
        $delay = max(0, min(5000, (int) $this->config->get('ai-chatbot.providers.openai.retry_delay_ms', 250)));

        try {
            $response = retry(
                $attempts,
                fn () => $agent->prompt(
                    $prompt,
                    provider: 'openai',
                    model: $model,
                    timeout: max(1, min(120, (int) $this->config->get('ai-chatbot.providers.openai.timeout', 30))),
                ),
                $delay,
                fn (Throwable $exception): bool => $this->isRetryable($exception),
            );
        } catch (Throwable $exception) {
            throw $this->normalize($exception);
        }

        $text = trim($response->text);
        if ($text === '') {
            throw new AiProviderException('malformed_response');
        }

        return new ProviderResponse(
            text: $text,
            inputTokens: $response->usage->inputTokens,
            outputTokens: $response->usage->outputTokens,
            model: $response->meta->model ?? $model,
            requestId: $response->raw?->header('x-request-id'),
        );
    }

    private function isRetryable(Throwable $exception): bool
    {
        return $exception instanceof ProviderConnectionException
            || $exception instanceof ProviderOverloadedException
            || $exception instanceof RateLimitedException;
    }

    private function normalize(Throwable $exception): AiProviderException
    {
        if ($exception instanceof AiProviderException) {
            return $exception;
        }

        $code = match (true) {
            $exception instanceof RateLimitedException => 'rate_limited',
            $exception instanceof InsufficientCreditsException => 'insufficient_credits',
            $exception instanceof ProviderConnectionException => 'connection_failed',
            $exception instanceof ProviderOverloadedException => 'provider_unavailable',
            $exception instanceof FailoverableException => 'provider_unavailable',
            default => 'provider_error',
        };

        return new AiProviderException($code, $this->isRetryable($exception), $exception);
    }
}
