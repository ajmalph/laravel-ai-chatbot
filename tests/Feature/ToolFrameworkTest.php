<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tests\Feature;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Pgs\LaravelAiChatbot\Contracts\ToolInterface;
use Pgs\LaravelAiChatbot\Tests\TestCase;
use Pgs\LaravelAiChatbot\Tools\ToolExecutionBudget;
use Pgs\LaravelAiChatbot\Tools\ToolExecutor;
use Pgs\LaravelAiChatbot\Tools\ToolRegistry;

final class ToolFrameworkTest extends TestCase
{
    public function test_registration_validates_name_schema_and_explicit_authorization(): void
    {
        $registry = app(ToolRegistry::class);
        $registry->register('public_lookup', $this->tool('public_lookup'));

        self::assertTrue($registry->has('public_lookup'));

        $this->expectException(InvalidArgumentException::class);
        $registry->register('bad_schema', $this->tool('bad_schema', schema: [
            'type' => 'object', 'properties' => ['query' => ['type' => 'string']], 'required' => [],
        ]));
    }

    public function test_unauthorized_malformed_unknown_excessive_and_failed_calls_are_safe_and_audited(): void
    {
        $registry = app(ToolRegistry::class);
        $registry->register('private_lookup', $this->tool('private_lookup', authorization: ['abilities' => ['view-private']]));
        $registry->register('failing_lookup', $this->tool('failing_lookup', throws: true));
        $executor = app(ToolExecutor::class);

        $unauthorized = $executor->execute('private_lookup', ['query' => 'secret-value'], 'call_1', null, new ToolExecutionBudget(4, 1000));
        $malformed = $executor->execute('private_lookup', ['wrong' => 'value'], 'call_2', null, new ToolExecutionBudget(4, 1000));
        $unknown = $executor->execute('unknown_lookup', [], 'call_3', null, new ToolExecutionBudget(4, 1000));
        $failed = $executor->execute('failing_lookup', ['query' => 'do not log me'], 'call_4', null, new ToolExecutionBudget(4, 1000));
        $budget = new ToolExecutionBudget(1, 1000);
        $executor->execute('failing_lookup', ['query' => 'first'], 'call_5', null, $budget);
        $excessive = $executor->execute('failing_lookup', ['query' => 'second'], 'call_6', null, $budget);

        self::assertSame('unauthorized', $unauthorized->errorCode);
        self::assertSame('invalid_arguments', $malformed->errorCode);
        self::assertSame('unknown_tool', $unknown->errorCode);
        self::assertSame('execution_failed', $failed->errorCode);
        self::assertSame('call_limit_exceeded', $excessive->errorCode);

        $logs = DB::table(config('ai-chatbot.tables.tool_logs'))->get();
        self::assertCount(6, $logs);
        self::assertNull($logs->first()->result);
        self::assertStringNotContainsString('secret-value', (string) $logs->first()->arguments);
        self::assertStringNotContainsString('do not log me', $logs->toJson());
    }

    public function test_result_is_sanitized_and_bounded(): void
    {
        $tool = $this->tool('bounded_lookup', result: ['text' => '<script>bad()</script>'.str_repeat('ع', 500)]);
        app(ToolRegistry::class)->register('bounded_lookup', $tool);

        $result = app(ToolExecutor::class)->execute(
            'bounded_lookup',
            ['query' => 'safe'],
            'call_bounded',
            null,
            new ToolExecutionBudget(2, 120),
        );

        self::assertTrue($result->successful());
        self::assertLessThanOrEqual(120, mb_strlen($result->output));
        self::assertStringNotContainsString('<script>', $result->output);
    }

    /** @param array<string, mixed>|null $schema
     * @param  array<string, mixed>  $authorization
     */
    private function tool(
        string $name,
        ?array $schema = null,
        array $authorization = ['public' => true],
        bool $throws = false,
        mixed $result = ['ok' => true],
    ): ToolInterface {
        return new class($name, $schema, $authorization, $throws, $result) implements ToolInterface
        {
            public function __construct(
                private readonly string $toolName,
                private readonly ?array $schemaDefinition,
                private readonly array $authorization,
                private readonly bool $throws,
                private readonly mixed $result,
            ) {}

            public function name(): string
            {
                return $this->toolName;
            }

            public function description(): string
            {
                return 'A test-only read-only lookup.';
            }

            public function argumentsSchema(): array
            {
                return $this->schemaDefinition ?? [
                    'type' => 'object',
                    'properties' => ['query' => ['type' => 'string', 'maxLength' => 100]],
                    'required' => ['query'],
                    'additionalProperties' => false,
                ];
            }

            public function authorizationRequirements(): array
            {
                return $this->authorization;
            }

            public function executeReadOnly(array $arguments): mixed
            {
                if ($this->throws) {
                    throw new \RuntimeException('Sensitive failure details.');
                }

                return $this->result;
            }

            public function maximumResultCharacters(): int
            {
                return 500;
            }
        };
    }
}
