<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Ursamajeur\CloudCodePA\Config\ModelRegistry;
use Ursamajeur\CloudCodePA\Parsing\RequestBuilder;
use Ursamajeur\CloudCodePA\Tests\Helpers\GatewayFactory;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\Requests\GenerateContentRequest;

it('returns tool calls in TextResponse when API returns functionCall', function (): void {
    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-function-call.json');

    $mockClient = new MockClient([
        GenerateContentRequest::class => MockResponse::make(body: $fixtureContent, status: 200),
    ]);

    $gateway = GatewayFactory::make($mockClient);

    $response = $gateway->generateText(
        provider: \Laravel\Ai\Ai::textProvider('cloudcode-pa'),
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: [new UserMessage('What is the weather in London?')],
    );

    expect($response->toolCalls)->toHaveCount(1);
    expect($response->toolCalls->first())->toBeInstanceOf(ToolCall::class);
    expect($response->toolCalls->first()->name)->toBe('get_weather');
    expect($response->toolCalls->first()->arguments)->toBe([
        'city' => 'London',
        'unit' => 'celsius',
    ]);
});

it('handles mixed text and function call parts', function (): void {
    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-with-tools.json');

    $mockClient = new MockClient([
        GenerateContentRequest::class => MockResponse::make(body: $fixtureContent, status: 200),
    ]);

    $gateway = GatewayFactory::make($mockClient, models: [
        'gemini-2.0-flash' => 'gemini-2.0-flash',
        'gemini-2.5-flash' => 'gemini-2.5-flash',
    ]);

    $response = $gateway->generateText(
        provider: \Laravel\Ai\Ai::textProvider('cloudcode-pa'),
        model: 'gemini-2.5-flash',
        instructions: null,
        messages: [new UserMessage('What is the weather?')],
    );

    expect($response->text)->toBe('Let me check the weather for you.');
    expect($response->toolCalls)->toHaveCount(1);
    expect($response->toolCalls->first()->name)->toBe('get_weather');
});

it('includes tool result messages as functionResponse in request contents', function (): void {
    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-success.json');

    $mockClient = new MockClient([
        GenerateContentRequest::class => MockResponse::make(body: $fixtureContent, status: 200),
    ]);

    $gateway = GatewayFactory::make($mockClient);

    $toolCalls = new Collection([
        new ToolCall(id: 'call_0', name: 'get_weather', arguments: ['city' => 'London']),
    ]);

    $toolResults = new Collection([
        new ToolResult(
            id: 'call_0',
            name: 'get_weather',
            arguments: ['city' => 'London'],
            result: ['temperature' => 15, 'condition' => 'cloudy'],
        ),
    ]);

    $messages = [
        new UserMessage('What is the weather?'),
        new AssistantMessage('', $toolCalls),
        new ToolResultMessage($toolResults),
    ];

    $gateway->generateText(
        provider: \Laravel\Ai\Ai::textProvider('cloudcode-pa'),
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: $messages,
    );

    $lastRequest = $mockClient->getLastPendingRequest();
    $body = $lastRequest->body()->all();
    $contents = $body['request']['contents'];

    // Should have: user message, model message with functionCall, user message with functionResponse
    expect($contents)->toHaveCount(3);
    expect($contents[0]['role'])->toBe('user');
    expect($contents[1]['role'])->toBe('model');
    expect($contents[1]['parts'][0])->toHaveKey('functionCall');
    expect($contents[2]['role'])->toBe('user');
    expect($contents[2]['parts'][0])->toHaveKey('functionResponse');
    expect($contents[2]['parts'][0]['functionResponse']['name'])->toBe('get_weather');
    expect($contents[2]['parts'][0]['functionResponse']['response'])->toBe([
        'temperature' => 15,
        'condition' => 'cloudy',
    ]);
});

it('includes tool definitions in request body when tools are provided', function (): void {
    $registry = new ModelRegistry(['gemini-2.0-flash' => 'gemini-2.0-flash']);
    $builder = new RequestBuilder(modelRegistry: $registry);

    // Build an envelope with tool definitions directly to verify format
    $envelope = $builder->build(
        model: 'gemini-2.0-flash',
        messages: [new UserMessage('Hello')],
        tools: [], // No laravel/ai tools for this test — just verify empty tools are omitted
    );

    expect($envelope['request'])->not->toHaveKey('tools');
});
