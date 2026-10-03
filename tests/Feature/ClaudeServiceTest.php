<?php

namespace Tests\Feature;

use Anthropic\Core\Exceptions\APIException;
use App\Services\Claude;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\TestCase;
use UnexpectedValueException;

class ClaudeServiceTest extends TestCase
{
    /** @var array<int, array{request: RequestInterface}> */
    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.anthropic.key' => 'test-key', 'services.anthropic.model' => 'claude-opus-5-5']);
    }

    /** A Claude service whose HTTP calls are answered by the given responses, in order. */
    private function claudeReplying(Response ...$responses): Claude
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new Claude(['transporter' => new GuzzleClient(['handler' => $stack]), 'maxRetries' => 0]);
    }

    private function message(string $text, string $stopReason = 'end_turn', array $extra = []): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5-5',
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
        ] + $extra));
    }

    private array $schema = ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok']];

    public function test_it_returns_the_decoded_json_answer(): void
    {
        $claude = $this->claudeReplying($this->message('{"ok": true}'));

        $this->assertSame(['ok' => true], $claude->generateJson('Hello', $this->schema));
    }

    public function test_it_sends_the_key_model_prompt_and_schema(): void
    {
        $this->claudeReplying($this->message('{"ok": true}'))->generateJson('Препоръчай книги', $this->schema);

        $request = $this->history[0]['request'];
        $body = json_decode((string) $request->getBody(), true);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/messages', $request->getUri()->getPath());
        $this->assertSame('test-key', $request->getHeaderLine('x-api-key'));
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame('Препоръчай книги', $body['messages'][0]['content']);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertSame($this->schema, $body['output_config']['format']['schema']);
    }

    public function test_the_model_is_configurable(): void
    {
        config(['services.anthropic.model' => 'claude-sonnet-5-5']);

        $this->claudeReplying($this->message('{"ok": true}'))->generateJson('Hi', $this->schema);

        $this->assertSame('claude-sonnet-5-5', json_decode((string) $this->history[0]['request']->getBody(), true)['model']);
    }

    public function test_a_missing_key_fails_before_any_request(): void
    {
        config(['services.anthropic.key' => null]);

        $this->expectException(RuntimeException::class);

        try {
            $this->claudeReplying()->generateJson('Hi', $this->schema);
        } finally {
            $this->assertSame([], $this->history);
        }
    }

    public function test_invalid_json_is_an_error(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->claudeReplying($this->message('not json at all'))->generateJson('Hi', $this->schema);
    }

    public function test_a_refusal_is_an_error(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('declined');

        $this->claudeReplying($this->message('', 'refusal', [
            'stop_details' => ['type' => 'refusal', 'category' => null, 'explanation' => null],
        ]))->generateJson('Hi', $this->schema);
    }

    public function test_api_errors_are_raised(): void
    {
        $this->expectException(APIException::class);

        $this->claudeReplying(new Response(429, ['Content-Type' => 'application/json'], json_encode([
            'type' => 'error', 'error' => ['type' => 'rate_limit_error', 'message' => 'slow down'],
        ])))->generateJson('Hi', $this->schema);
    }
}
