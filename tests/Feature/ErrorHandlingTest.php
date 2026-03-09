<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Ursamajeur\CloudCodePA\Exceptions\ApiException;
use Ursamajeur\CloudCodePA\Exceptions\AuthenticationException;
use Ursamajeur\CloudCodePA\Tests\Helpers\GatewayFactory;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\Requests\GenerateContentRequest;

function createProvider(): \Laravel\Ai\Contracts\Providers\TextProvider
{
    return \Laravel\Ai\Ai::textProvider('cloudcode-pa');
}

it('throws ApiException::rateLimited on 429 response', function (): void {
    $mockClient = new MockClient([
        GenerateContentRequest::class => MockResponse::make(
            body: file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-error-429.json'),
            status: 429,
        ),
    ]);

    $gateway = GatewayFactory::make($mockClient);

    expect(fn () => $gateway->generateText(
        provider: createProvider(),
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: [new \Laravel\Ai\Messages\UserMessage('Hello')],
    ))->toThrow(ApiException::class, 'rate limit');
});

it('throws AuthenticationException on 401 response', function (): void {
    $mockClient = new MockClient([
        GenerateContentRequest::class => MockResponse::make(
            body: file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-error-401.json'),
            status: 401,
        ),
    ]);

    $gateway = GatewayFactory::make($mockClient);

    expect(fn () => $gateway->generateText(
        provider: createProvider(),
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: [new \Laravel\Ai\Messages\UserMessage('Hello')],
    ))->toThrow(AuthenticationException::class);
});

it('throws ApiException::serverError on 500 response', function (): void {
    $mockClient = new MockClient([
        GenerateContentRequest::class => MockResponse::make(
            body: file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-error-500.json'),
            status: 500,
        ),
    ]);

    $gateway = GatewayFactory::make($mockClient);

    try {
        $gateway->generateText(
            provider: createProvider(),
            model: 'gemini-2.0-flash',
            instructions: null,
            messages: [new \Laravel\Ai\Messages\UserMessage('Hello')],
        );
        test()->fail('Expected ApiException');
    } catch (ApiException $e) {
        expect($e->statusCode)->toBe(500);
        expect($e->model)->toBe('gemini-2.0-flash');
    }
});

it('throws ApiException::clientError on 400 response', function (): void {
    $mockClient = new MockClient([
        GenerateContentRequest::class => MockResponse::make(
            body: '{"error":{"message":"Invalid request"}}',
            status: 400,
        ),
    ]);

    $gateway = GatewayFactory::make($mockClient);

    try {
        $gateway->generateText(
            provider: createProvider(),
            model: 'gemini-2.0-flash',
            instructions: null,
            messages: [new \Laravel\Ai\Messages\UserMessage('Hello')],
        );
        test()->fail('Expected ApiException');
    } catch (ApiException $e) {
        expect($e->statusCode)->toBe(400);
        expect($e->errorMessage)->toBe('Invalid request');
    }
});

it('debug middleware logs request with redacted headers', function (): void {
    Log::shouldReceive('debug')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            if ($message !== 'CloudCode-PA Request') {
                return false;
            }

            // Verify Authorization header is redacted
            return ($context['headers']['Authorization'] ?? '') === 'Bearer [REDACTED]';
        });

    Log::shouldReceive('debug')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'CloudCode-PA Response' && $context['status'] === 200;
        });

    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-success.json');

    $mockClient = new MockClient([
        GenerateContentRequest::class => MockResponse::make(body: $fixtureContent, status: 200),
    ]);

    $gateway = GatewayFactory::make($mockClient, debug: true);

    $gateway->generateText(
        provider: createProvider(),
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: [new \Laravel\Ai\Messages\UserMessage('Hello')],
    );
});

it('throws TransportException::timeout on connection timeout', function (): void {
    $timeoutException = \Ursamajeur\CloudCodePA\Exceptions\TransportException::timeout();
    expect($timeoutException)->toBeInstanceOf(\Ursamajeur\CloudCodePA\Exceptions\TransportException::class);
    expect($timeoutException->getMessage())->toContain('timeout');

    $connectionException = \Ursamajeur\CloudCodePA\Exceptions\TransportException::connectionFailed();
    expect($connectionException)->toBeInstanceOf(\Ursamajeur\CloudCodePA\Exceptions\TransportException::class);
    expect($connectionException->getMessage())->toContain('connection');
});

it('non-debug mode produces no log output', function (): void {
    Log::shouldReceive('debug')->never();

    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-success.json');

    $mockClient = new MockClient([
        GenerateContentRequest::class => MockResponse::make(body: $fixtureContent, status: 200),
    ]);

    $gateway = GatewayFactory::make($mockClient, debug: false);

    $gateway->generateText(
        provider: createProvider(),
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: [new \Laravel\Ai\Messages\UserMessage('Hello')],
    );
});
