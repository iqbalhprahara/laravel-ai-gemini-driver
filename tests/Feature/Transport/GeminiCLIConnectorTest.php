<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Ursamajeur\CloudCodePA\Auth\CloudCodeAuthenticator;
use Ursamajeur\CloudCodePA\Contracts\CredentialStoreInterface;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\GeminiCLIConnector;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\Requests\GenerateContentRequest;

it('resolves correct base URL from constructor', function (): void {
    $credentialStore = Mockery::mock(CredentialStoreInterface::class);
    $authenticator = new CloudCodeAuthenticator(
        credentialStore: $credentialStore,
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret',
    );

    $connector = new GeminiCLIConnector(
        baseUrl: 'https://cloudcode-pa.googleapis.com/v1internal',
        cloudCodeAuth: $authenticator,
    );

    expect($connector->resolveBaseUrl())->toBe('https://cloudcode-pa.googleapis.com/v1internal');
});

it('sends GenerateContentRequest with POST to :generateContent endpoint', function (): void {
    $credentialStore = Mockery::mock(CredentialStoreInterface::class);
    $credentialStore->shouldReceive('isExpired')->andReturn(false);
    $credentialStore->shouldReceive('getAccessToken')->andReturn('test-token-value');

    $authenticator = new CloudCodeAuthenticator(
        credentialStore: $credentialStore,
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret',
    );

    $connector = new GeminiCLIConnector(
        baseUrl: 'https://cloudcode-pa.googleapis.com/v1internal',
        cloudCodeAuth: $authenticator,
    );

    $body = [
        'model' => 'gemini-2.0-flash',
        'request' => [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => 'Hello!']]],
            ],
        ],
    ];

    $request = new GenerateContentRequest($body);

    expect($request->resolveEndpoint())->toBe(':generateContent');
    expect($request->getMethod()->value)->toBe('POST');

    $fixtureContent = file_get_contents(__DIR__.'/../../Fixtures/responses/generate-content-success.json');

    $mockClient = new MockClient([
        GenerateContentRequest::class => MockResponse::make(
            body: $fixtureContent,
            status: 200,
        ),
    ]);

    $connector->withMockClient($mockClient);

    $response = $connector->send($request);

    expect($response->status())->toBe(200);
    expect($response->json('candidates.0.content.parts.0.text'))->toBe('Hello! How can I help you today?');
});

it('includes Content-Type header in requests', function (): void {
    $credentialStore = Mockery::mock(CredentialStoreInterface::class);
    $credentialStore->shouldReceive('isExpired')->andReturn(false);
    $credentialStore->shouldReceive('getAccessToken')->andReturn('test-token-value');

    $authenticator = new CloudCodeAuthenticator(
        credentialStore: $credentialStore,
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret',
    );

    $connector = new GeminiCLIConnector(
        baseUrl: 'https://cloudcode-pa.googleapis.com/v1internal',
        cloudCodeAuth: $authenticator,
    );

    $request = new GenerateContentRequest(['model' => 'test']);

    $mockClient = new MockClient([
        GenerateContentRequest::class => MockResponse::make(body: '{}', status: 200),
    ]);

    $connector->withMockClient($mockClient);

    $response = $connector->send($request);

    $lastRequest = $mockClient->getLastPendingRequest();
    expect($lastRequest->headers()->get('Content-Type'))->toBe('application/json');
});

it('attaches Authorization header via CloudCodeAuthenticator', function (): void {
    $credentialStore = Mockery::mock(CredentialStoreInterface::class);
    $credentialStore->shouldReceive('isExpired')->andReturn(false);
    $credentialStore->shouldReceive('getAccessToken')->andReturn('valid-access-token');

    $authenticator = new CloudCodeAuthenticator(
        credentialStore: $credentialStore,
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret',
    );

    $connector = new GeminiCLIConnector(
        baseUrl: 'https://cloudcode-pa.googleapis.com/v1internal',
        cloudCodeAuth: $authenticator,
    );

    $request = new GenerateContentRequest(['model' => 'test']);

    $mockClient = new MockClient([
        GenerateContentRequest::class => MockResponse::make(body: '{}', status: 200),
    ]);

    $connector->withMockClient($mockClient);

    $connector->send($request);

    $lastRequest = $mockClient->getLastPendingRequest();
    expect($lastRequest->headers()->get('Authorization'))->toBe('Bearer valid-access-token');
});
