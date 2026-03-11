<?php

declare(strict_types=1);

use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\TextEnd;
use Laravel\Ai\Streaming\Events\TextStart;
use Laravel\Ai\Streaming\Events\ToolCall as ToolCallEvent;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Ursamajeur\CloudCodePA\Tests\Helpers\GatewayFactory;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\Requests\StreamContentRequest;

beforeEach(function (): void {
    config()->set(
        'cloudcode-pa.auth.credentials_path',
        __DIR__.'/../Fixtures/credentials/valid-credentials.json',
    );
});

afterEach(function (): void {
    MockClient::destroyGlobal();
});

it('streams multiple text chunks through the gateway', function (): void {
    $sseBody = file_get_contents(__DIR__.'/../Fixtures/streams/stream-success.txt');

    $mockClient = new MockClient([
        StreamContentRequest::class => MockResponse::make(body: $sseBody, status: 200),
    ]);

    $gateway = GatewayFactory::make($mockClient);

    $provider = \Mockery::mock(\Laravel\Ai\Contracts\Providers\TextProvider::class);
    $provider->shouldReceive('name')->andReturn('cloudcode-pa');

    $events = iterator_to_array($gateway->streamText(
        invocationId: 'test-invocation',
        provider: $provider,
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: [new \Laravel\Ai\Messages\UserMessage('Hello!')],
    ));

    // Expected event sequence: StreamStart, TextStart, TextDelta×3, TextEnd, StreamEnd
    expect($events[0])->toBeInstanceOf(StreamStart::class);
    expect($events[0]->provider)->toBe('cloudcode-pa');
    expect($events[0]->model)->toBe('gemini-2.0-flash');

    expect($events[1])->toBeInstanceOf(TextStart::class);

    // Three text deltas from the stream-success.txt fixture
    $textDeltas = array_values(array_filter($events, fn ($e) => $e instanceof TextDelta));
    expect($textDeltas)->toHaveCount(3);
    expect($textDeltas[0]->delta)->toBe('Hello');
    expect($textDeltas[1]->delta)->toBe(' world');
    expect($textDeltas[2]->delta)->toBe('!');

    // TextEnd and StreamEnd at the end
    $lastTwo = array_slice($events, -2);
    expect($lastTwo[0])->toBeInstanceOf(TextEnd::class);
    expect($lastTwo[1])->toBeInstanceOf(StreamEnd::class);
});

it('includes usage metadata on stream end', function (): void {
    $sseBody = file_get_contents(__DIR__.'/../Fixtures/streams/stream-success.txt');

    $mockClient = new MockClient([
        StreamContentRequest::class => MockResponse::make(body: $sseBody, status: 200),
    ]);

    $gateway = GatewayFactory::make($mockClient);

    $provider = \Mockery::mock(\Laravel\Ai\Contracts\Providers\TextProvider::class);
    $provider->shouldReceive('name')->andReturn('cloudcode-pa');

    $events = iterator_to_array($gateway->streamText(
        invocationId: 'test-invocation',
        provider: $provider,
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: [new \Laravel\Ai\Messages\UserMessage('Hello!')],
    ));

    $streamEnd = end($events);
    expect($streamEnd)->toBeInstanceOf(StreamEnd::class);
    expect($streamEnd->usage)->toBeInstanceOf(Usage::class);
    expect($streamEnd->usage->promptTokens)->toBe(5);
    expect($streamEnd->usage->completionTokens)->toBe(3);
    expect($streamEnd->reason)->toBe('stop');
});

it('yields tool call events from streaming chunks', function (): void {
    $sseBody = file_get_contents(__DIR__.'/../Fixtures/streams/stream-with-tool-calls.txt');

    $mockClient = new MockClient([
        StreamContentRequest::class => MockResponse::make(body: $sseBody, status: 200),
    ]);

    $gateway = GatewayFactory::make($mockClient);

    $provider = \Mockery::mock(\Laravel\Ai\Contracts\Providers\TextProvider::class);
    $provider->shouldReceive('name')->andReturn('cloudcode-pa');

    $events = iterator_to_array($gateway->streamText(
        invocationId: 'test-invocation',
        provider: $provider,
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: [new \Laravel\Ai\Messages\UserMessage('What is the weather?')],
    ));

    $toolCallEvents = array_values(array_filter($events, fn ($e) => $e instanceof ToolCallEvent));
    expect($toolCallEvents)->toHaveCount(1);
    expect($toolCallEvents[0]->toolCall->name)->toBe('get_weather');
    expect($toolCallEvents[0]->toolCall->arguments)->toBe(['location' => 'London']);
});

it('sends request to streamGenerateContent endpoint', function (): void {
    $sseBody = file_get_contents(__DIR__.'/../Fixtures/streams/stream-success.txt');

    $mockClient = new MockClient([
        StreamContentRequest::class => MockResponse::make(body: $sseBody, status: 200),
    ]);

    $gateway = GatewayFactory::make($mockClient);

    $provider = \Mockery::mock(\Laravel\Ai\Contracts\Providers\TextProvider::class);
    $provider->shouldReceive('name')->andReturn('cloudcode-pa');

    // Consume the generator
    iterator_to_array($gateway->streamText(
        invocationId: 'test-invocation',
        provider: $provider,
        model: 'gemini-2.0-flash',
        instructions: 'Be helpful.',
        messages: [new \Laravel\Ai\Messages\UserMessage('Hello!')],
    ));

    $mockClient->assertSent(function (StreamContentRequest $request): bool {
        $body = $request->body()->all();

        return $request->resolveEndpoint() === ':streamGenerateContent'
            && $body['model'] === 'gemini-2.0-flash'
            && isset($body['request']['systemInstruction']);
    });
});

it('combines text deltas into full text', function (): void {
    $sseBody = file_get_contents(__DIR__.'/../Fixtures/streams/stream-success.txt');

    $mockClient = new MockClient([
        StreamContentRequest::class => MockResponse::make(body: $sseBody, status: 200),
    ]);

    $gateway = GatewayFactory::make($mockClient);

    $provider = \Mockery::mock(\Laravel\Ai\Contracts\Providers\TextProvider::class);
    $provider->shouldReceive('name')->andReturn('cloudcode-pa');

    $events = iterator_to_array($gateway->streamText(
        invocationId: 'test-invocation',
        provider: $provider,
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: [new \Laravel\Ai\Messages\UserMessage('Hello!')],
    ));

    $combinedText = TextDelta::combine($events);
    expect($combinedText)->toBe('Hello world!');
});

it('provider supports streaming via StreamsText trait and CloudCodeGateway', function (): void {
    $provider = \Laravel\Ai\Ai::textProvider('cloudcode-pa');

    // Verify the provider uses the StreamsText trait (which provides stream() method)
    $traits = class_uses_recursive($provider);
    expect($traits)->toContain(\Laravel\Ai\Providers\Concerns\StreamsText::class);

    // Verify the gateway is our CloudCodeGateway (which implements streamText)
    expect($provider->textGateway())
        ->toBeInstanceOf(\Ursamajeur\CloudCodePA\Gateway\CloudCodeGateway::class);
});

it('handles API errors during streaming', function (): void {
    $mockClient = new MockClient([
        StreamContentRequest::class => MockResponse::make(
            body: '{"error":{"code":429,"message":"Rate limited"}}',
            status: 429,
        ),
    ]);

    $gateway = GatewayFactory::make($mockClient);

    $provider = \Mockery::mock(\Laravel\Ai\Contracts\Providers\TextProvider::class);
    $provider->shouldReceive('name')->andReturn('cloudcode-pa');

    iterator_to_array($gateway->streamText(
        invocationId: 'test-invocation',
        provider: $provider,
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: [new \Laravel\Ai\Messages\UserMessage('Hello!')],
    ));
})->throws(\Ursamajeur\CloudCodePA\Exceptions\ApiException::class);
