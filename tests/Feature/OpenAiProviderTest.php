<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Pgs\LaravelAiChatbot\Contracts\ToolInterface;
use Pgs\LaravelAiChatbot\Exceptions\AiProviderException;
use Pgs\LaravelAiChatbot\Providers\OpenAiChatAgent;
use Pgs\LaravelAiChatbot\Providers\OpenAiProvider;
use Pgs\LaravelAiChatbot\Tests\TestCase;
use Pgs\LaravelAiChatbot\Tools\ToolRegistry;

final class OpenAiProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config()->set('ai-chatbot.providers.openai.api_key', 'test-key');
        config()->set('ai-chatbot.providers.openai.model', 'gpt-test');
        config()->set('ai-chatbot.providers.openai.attempts', 1);
    }

    public function test_it_uses_the_responses_api_with_a_mocked_http_response(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_test',
                'model' => 'gpt-test',
                'status' => 'completed',
                'output' => [[
                    'id' => 'msg_test',
                    'type' => 'message',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'HTTP mocked answer [S1].',
                        'annotations' => [],
                    ]],
                ]],
                'usage' => ['input_tokens' => 12, 'output_tokens' => 4],
            ], 200, ['x-request-id' => 'request_test']),
        ]);

        $response = app(OpenAiProvider::class)->generate('System instructions', 'Question');

        self::assertSame('HTTP mocked answer [S1].', $response->text);
        self::assertSame('request_test', $response->requestId);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['model'] === 'gpt-test'
                && $request['max_output_tokens'] === 700
                && $request['store'] === false;
        });
    }

    public function test_it_completes_the_function_call_cycle_with_matching_call_id(): void
    {
        Http::fakeSequence('https://api.openai.com/v1/responses')
            ->push([
                'id' => 'resp_tool', 'model' => 'gpt-test', 'status' => 'completed',
                'output' => [[
                    'id' => 'fc_test', 'call_id' => 'call_test', 'type' => 'function_call',
                    'name' => 'public_lookup', 'arguments' => '{"query":"events"}', 'status' => 'completed',
                ]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 3],
            ])
            ->push([
                'id' => 'resp_final', 'model' => 'gpt-test', 'status' => 'completed',
                'output' => [[
                    'id' => 'msg_final', 'type' => 'message', 'status' => 'completed', 'role' => 'assistant',
                    'content' => [['type' => 'output_text', 'text' => 'Final tool-grounded answer.', 'annotations' => []]],
                ]],
                'usage' => ['input_tokens' => 20, 'output_tokens' => 6],
            ]);

        $tool = new class implements ToolInterface
        {
            public function name(): string
            {
                return 'public_lookup';
            }

            public function description(): string
            {
                return 'Look up public information.';
            }

            public function argumentsSchema(): array
            {
                return [
                    'type' => 'object',
                    'properties' => ['query' => ['type' => 'string', 'maxLength' => 100]],
                    'required' => ['query'],
                    'additionalProperties' => false,
                ];
            }

            public function authorizationRequirements(): array
            {
                return ['public' => true];
            }

            public function executeReadOnly(array $arguments): array
            {
                return ['items' => [['title' => 'Event']]];
            }

            public function maximumResultCharacters(): int
            {
                return 1000;
            }
        };

        app(ToolRegistry::class)->register('public_lookup', $tool);
        $response = app(OpenAiProvider::class)->generate('Instructions', 'Question', tools: [$tool]);

        self::assertSame('Final tool-grounded answer.', $response->text);
        self::assertSame(30, $response->inputTokens);
        self::assertSame(9, $response->outputTokens);
        Http::assertSentCount(2);
        $requests = Http::recorded();
        $payload = json_encode($requests[1][0]->data(), JSON_UNESCAPED_SLASHES);
        self::assertStringContainsString('function_call_output', $payload);
        self::assertStringContainsString('call_test', $payload);
        self::assertStringContainsString('Event', $payload);
    }

    public function test_it_maps_a_mocked_sdk_response_without_an_http_request(): void
    {
        OpenAiChatAgent::fake([
            new AgentResponse('invocation', 'Grounded answer [S1].', new TextUsage(42, 9), new Meta('openai', 'gpt-test')),
        ]);

        $response = app(OpenAiProvider::class)->generate('Instructions', 'Prompt', [
            ['role' => 'user', 'content' => 'Previous'],
        ]);

        self::assertSame('Grounded answer [S1].', $response->text);
        self::assertSame(42, $response->inputTokens);
        self::assertSame(9, $response->outputTokens);
        Http::assertNothingSent();
    }

    public function test_it_rejects_empty_mocked_responses(): void
    {
        OpenAiChatAgent::fake([
            new AgentResponse('invocation', ' ', new TextUsage, new Meta('openai', 'gpt-test')),
        ]);

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('could not complete');

        app(OpenAiProvider::class)->generate('Instructions', 'Prompt');
    }

    public function test_it_fails_safely_when_credentials_are_missing(): void
    {
        config()->set('ai-chatbot.providers.openai.api_key', null);

        try {
            app(OpenAiProvider::class)->generate('Instructions', 'Prompt');
            self::fail('Expected provider exception.');
        } catch (AiProviderException $exception) {
            self::assertSame('provider_not_configured', $exception->errorCode);
            self::assertStringNotContainsString('test-key', $exception->getMessage());
        }

        Http::assertNothingSent();
    }
}
