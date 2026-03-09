<?php

declare(strict_types=1);

use Laravel\Ai\Ai;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\Requests\GenerateContentRequest;

beforeEach(function (): void {
    // Point to the test credential fixture so the authenticator can load tokens
    config()->set(
        'cloudcode-pa.auth.credentials_path',
        __DIR__.'/../Fixtures/credentials/valid-credentials.json',
    );
});

afterEach(function (): void {
    MockClient::destroyGlobal();
});

it('generates text through the full gateway stack with MockClient', function (): void {
    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-success.json');

    $mockClient = MockClient::global([
        GenerateContentRequest::class => MockResponse::make(
            body: $fixtureContent,
            status: 200,
        ),
    ]);

    $provider = Ai::textProvider('cloudcode-pa');
    $gateway = $provider->textGateway();

    $response = $gateway->generateText(
        provider: $provider,
        model: 'gemini-2.0-flash',
        instructions: 'You are a helpful assistant.',
        messages: [new UserMessage('Hello!')],
    );

    expect($response)->toBeInstanceOf(TextResponse::class);
    expect($response->text)->toBe('Hello! How can I help you today?');
});

it('returns correct usage metadata in TextResponse', function (): void {
    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-success.json');

    MockClient::global([
        GenerateContentRequest::class => MockResponse::make(body: $fixtureContent, status: 200),
    ]);

    $provider = Ai::textProvider('cloudcode-pa');

    $response = $provider->textGateway()->generateText(
        provider: $provider,
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: [new UserMessage('Hello!')],
    );

    expect($response->usage)->toBeInstanceOf(Usage::class);
    expect($response->usage->promptTokens)->toBe(10);
    expect($response->usage->completionTokens)->toBe(25);
});

it('returns correct meta in TextResponse', function (): void {
    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-success.json');

    MockClient::global([
        GenerateContentRequest::class => MockResponse::make(body: $fixtureContent, status: 200),
    ]);

    $provider = Ai::textProvider('cloudcode-pa');

    $response = $provider->textGateway()->generateText(
        provider: $provider,
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: [new UserMessage('Hello!')],
    );

    expect($response->meta)->toBeInstanceOf(Meta::class);
    expect($response->meta->provider)->toBe('cloudcode-pa');
    expect($response->meta->model)->toBe('gemini-2.0-flash');
});

it('includes system instructions in the outgoing request body', function (): void {
    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-success.json');

    $mockClient = MockClient::global([
        GenerateContentRequest::class => MockResponse::make(body: $fixtureContent, status: 200),
    ]);

    $provider = Ai::textProvider('cloudcode-pa');

    $provider->textGateway()->generateText(
        provider: $provider,
        model: 'gemini-2.0-flash',
        instructions: 'You are a coding assistant.',
        messages: [new UserMessage('Write PHP code')],
    );

    $mockClient->assertSent(function (GenerateContentRequest $request): bool {
        $body = $request->body()->all();

        return isset($body['request']['systemInstruction']['parts'][0]['text'])
            && $body['request']['systemInstruction']['parts'][0]['text'] === 'You are a coding assistant.';
    });
});

it('resolves model alias to bare name in request body', function (): void {
    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-success.json');

    $mockClient = MockClient::global([
        GenerateContentRequest::class => MockResponse::make(body: $fixtureContent, status: 200),
    ]);

    $provider = Ai::textProvider('cloudcode-pa');

    $provider->textGateway()->generateText(
        provider: $provider,
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: [new UserMessage('Hello!')],
    );

    $mockClient->assertSent(function (GenerateContentRequest $request): bool {
        $body = $request->body()->all();

        return $body['model'] === 'gemini-2.0-flash';
    });
});

it('maps multi-turn conversation correctly in request', function (): void {
    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-success.json');

    $mockClient = MockClient::global([
        GenerateContentRequest::class => MockResponse::make(body: $fixtureContent, status: 200),
    ]);

    $provider = Ai::textProvider('cloudcode-pa');

    $messages = [
        new UserMessage('What is PHP?'),
        new \Laravel\Ai\Messages\AssistantMessage('PHP is a language.'),
        new UserMessage('Tell me more.'),
    ];

    $provider->textGateway()->generateText(
        provider: $provider,
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: $messages,
    );

    $mockClient->assertSent(function (GenerateContentRequest $request): bool {
        $body = $request->body()->all();
        $contents = $body['request']['contents'];

        return count($contents) === 3
            && $contents[0]['role'] === 'user'
            && $contents[1]['role'] === 'model'
            && $contents[2]['role'] === 'user';
    });
});
