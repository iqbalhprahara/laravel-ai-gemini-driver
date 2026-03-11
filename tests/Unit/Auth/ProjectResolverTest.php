<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Ursamajeur\CloudCodePA\Auth\CloudCodeAuthenticator;
use Ursamajeur\CloudCodePA\Auth\ProjectResolver;
use Ursamajeur\CloudCodePA\Contracts\CredentialStoreInterface;
use Ursamajeur\CloudCodePA\Exceptions\ApiException;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\GeminiCLIConnector;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\Requests\LoadCodeAssistRequest;

function createConnectorWithMock(MockClient $mockClient): GeminiCLIConnector
{
    $credentialStore = Mockery::mock(CredentialStoreInterface::class);
    $credentialStore->shouldReceive('isExpired')->andReturn(false);
    $credentialStore->shouldReceive('getAccessToken')->andReturn('test-token');

    $authenticator = new CloudCodeAuthenticator(
        credentialStore: $credentialStore,
        clientId: 'test-id',
        clientSecret: 'test-secret',
    );

    $connector = new GeminiCLIConnector(
        baseUrl: 'https://cloudcode-pa.googleapis.com/v1internal',
        cloudCodeAuth: $authenticator,
    );

    $connector->withMockClient($mockClient);

    return $connector;
}

afterEach(function (): void {
    MockClient::destroyGlobal();
});

it('resolves project ID from loadCodeAssist response', function (): void {
    $mockClient = new MockClient([
        LoadCodeAssistRequest::class => MockResponse::make(
            body: json_encode(['cloudaicompanionProject' => 'my-project-123']),
            status: 200,
        ),
    ]);

    $resolver = new ProjectResolver(
        connector: createConnectorWithMock($mockClient),
    );

    expect($resolver->resolve())->toBe('my-project-123');
});

it('caches the project ID after first resolution', function (): void {
    $mockClient = new MockClient([
        LoadCodeAssistRequest::class => MockResponse::make(
            body: json_encode(['cloudaicompanionProject' => 'cached-project']),
            status: 200,
        ),
    ]);

    $resolver = new ProjectResolver(
        connector: createConnectorWithMock($mockClient),
    );

    // First call — hits the API
    $first = $resolver->resolve();
    // Second call — should use cache (MockClient would throw if called again with no more responses)
    $second = $resolver->resolve();

    expect($first)->toBe('cached-project')
        ->and($second)->toBe('cached-project');
});

it('allows pre-setting project ID to skip API call', function (): void {
    $mockClient = new MockClient([
        LoadCodeAssistRequest::class => MockResponse::make(
            body: json_encode(['error' => 'should not be called']),
            status: 500,
        ),
    ]);

    $resolver = new ProjectResolver(
        connector: createConnectorWithMock($mockClient),
    );

    $resolver->setProjectId('preset-project');

    expect($resolver->resolve())->toBe('preset-project');
});

it('throws ApiException on failed loadCodeAssist response', function (): void {
    $mockClient = new MockClient([
        LoadCodeAssistRequest::class => MockResponse::make(
            body: json_encode(['error' => 'Internal error']),
            status: 500,
        ),
    ]);

    $resolver = new ProjectResolver(
        connector: createConnectorWithMock($mockClient),
    );

    expect(fn () => $resolver->resolve())
        ->toThrow(ApiException::class, 'Failed to resolve project ID');
});

it('throws ApiException when response lacks project ID', function (): void {
    $mockClient = new MockClient([
        LoadCodeAssistRequest::class => MockResponse::make(
            body: json_encode(['otherField' => 'value']),
            status: 200,
        ),
    ]);

    $resolver = new ProjectResolver(
        connector: createConnectorWithMock($mockClient),
    );

    expect(fn () => $resolver->resolve())
        ->toThrow(ApiException::class, 'loadCodeAssist did not return a cloudaicompanionProject');
});

it('throws ApiException when project ID is empty string', function (): void {
    $mockClient = new MockClient([
        LoadCodeAssistRequest::class => MockResponse::make(
            body: json_encode(['cloudaicompanionProject' => '']),
            status: 200,
        ),
    ]);

    $resolver = new ProjectResolver(
        connector: createConnectorWithMock($mockClient),
    );

    expect(fn () => $resolver->resolve())
        ->toThrow(ApiException::class, 'loadCodeAssist did not return a cloudaicompanionProject');
});
