<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Saloon\Contracts\ArrayStore;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Ursamajeur\CloudCodePA\Auth\CloudCodeAuthenticator;
use Ursamajeur\CloudCodePA\Auth\TokenRefreshRequest;
use Ursamajeur\CloudCodePA\Contracts\CredentialStoreInterface;
use Ursamajeur\CloudCodePA\Exceptions\AuthenticationException;

afterEach(function (): void {
    MockClient::destroyGlobal();
});

// AC #3 — Attaches Bearer header with current access token
it('attaches Bearer header with current access token', function (): void {
    $store = Mockery::mock(CredentialStoreInterface::class);
    $store->shouldReceive('isExpired')->once()->andReturn(false);
    $store->shouldReceive('getAccessToken')->once()->andReturn('ya29.valid-token');

    $authenticator = new CloudCodeAuthenticator(
        credentialStore: $store,
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret',
    );

    $pendingRequest = Mockery::mock(PendingRequest::class);
    $headers = Mockery::mock(ArrayStore::class);
    $pendingRequest->shouldReceive('headers')->once()->andReturn($headers);
    $headers->shouldReceive('add')->once()->with('Authorization', 'Bearer ya29.valid-token');

    $authenticator->set($pendingRequest);
});

// AC #1 — Triggers refresh when token is expired
it('triggers refresh when token is expired', function (): void {
    MockClient::global([
        TokenRefreshRequest::class => MockResponse::make([
            'access_token' => 'ya29.new-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
    ]);

    $store = Mockery::mock(CredentialStoreInterface::class);
    $store->shouldReceive('isExpired')->once()->andReturn(true);
    $store->shouldReceive('getRefreshToken')->once()->andReturn('1//refresh');
    $store->shouldReceive('updateCredentials')->once()->withArgs(function (string $accessToken, string $refreshToken, int $expiresAt): bool {
        return $accessToken === 'ya29.new-token'
            && $refreshToken === '1//refresh'
            && $expiresAt > time();
    });
    $store->shouldReceive('getAccessToken')->once()->andReturn('ya29.new-token');

    $authenticator = new CloudCodeAuthenticator(
        credentialStore: $store,
        clientId: 'test-client-id',
        clientSecret: 'test-secret',
    );

    $pendingRequest = Mockery::mock(PendingRequest::class);
    $headers = Mockery::mock(ArrayStore::class);
    $pendingRequest->shouldReceive('headers')->once()->andReturn($headers);
    $headers->shouldReceive('add')->once()->with('Authorization', 'Bearer ya29.new-token');

    $authenticator->set($pendingRequest);
});

// AC #2 — Successful refresh updates CredentialStore
it('updates credential store on successful refresh', function (): void {
    MockClient::global([
        TokenRefreshRequest::class => MockResponse::make([
            'access_token' => 'ya29.refreshed',
            'expires_in' => 7200,
            'token_type' => 'Bearer',
            'refresh_token' => '1//new-refresh',
        ]),
    ]);

    $store = Mockery::mock(CredentialStoreInterface::class);
    $store->shouldReceive('getRefreshToken')->once()->andReturn('1//old-refresh');
    $store->shouldReceive('updateCredentials')->once()->withArgs(function (string $accessToken, string $refreshToken, int $expiresAt): bool {
        return $accessToken === 'ya29.refreshed'
            && $refreshToken === '1//new-refresh'
            && $expiresAt > time();
    });

    $authenticator = new CloudCodeAuthenticator(
        credentialStore: $store,
        clientId: 'test-client-id',
        clientSecret: 'test-secret',
    );

    $authenticator->refreshToken();
});

// AC #4 — Failed refresh throws AuthenticationException::refreshFailed()
it('throws refreshFailed on failed refresh', function (): void {
    MockClient::global([
        TokenRefreshRequest::class => MockResponse::make([
            'error' => 'invalid_grant',
            'error_description' => 'Token has been revoked',
        ], 400),
    ]);

    $store = Mockery::mock(CredentialStoreInterface::class);
    $store->shouldReceive('getRefreshToken')->once()->andReturn('1//revoked');

    $authenticator = new CloudCodeAuthenticator(
        credentialStore: $store,
        clientId: 'test-client-id',
        clientSecret: 'test-secret',
    );

    $authenticator->refreshToken();
})->throws(AuthenticationException::class, 'Token has been revoked');

// AC #6 — Token values never appear in log output
it('never logs token values', function (): void {
    MockClient::global([
        TokenRefreshRequest::class => MockResponse::make([
            'access_token' => 'ya29.secret-token-value',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
    ]);

    Log::spy();

    $store = Mockery::mock(CredentialStoreInterface::class);
    $store->shouldReceive('getRefreshToken')->once()->andReturn('1//secret-refresh');
    $store->shouldReceive('updateCredentials')->once();

    $authenticator = new CloudCodeAuthenticator(
        credentialStore: $store,
        clientId: 'test-client-id',
        clientSecret: 'test-secret',
        debug: true,
    );

    $authenticator->refreshToken();

    Log::shouldHaveReceived('debug')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            // Verify message and context never contain token values
            $serialized = $message.json_encode($context);

            return str_contains($message, 'Token refreshed')
                && isset($context['type'], $context['expires'])
                && ! str_contains($serialized, 'ya29.secret-token-value')
                && ! str_contains($serialized, '1//secret-refresh');
        });
});

// AC #6 — No logging when debug is disabled
it('does not log when debug is disabled', function (): void {
    MockClient::global([
        TokenRefreshRequest::class => MockResponse::make([
            'access_token' => 'ya29.new',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
    ]);

    Log::spy();

    $store = Mockery::mock(CredentialStoreInterface::class);
    $store->shouldReceive('getRefreshToken')->once()->andReturn('1//refresh');
    $store->shouldReceive('updateCredentials')->once();

    $authenticator = new CloudCodeAuthenticator(
        credentialStore: $store,
        clientId: 'test-client-id',
        clientSecret: 'test-secret',
        debug: false,
    );

    $authenticator->refreshToken();

    Log::shouldNotHaveReceived('debug');
});

// AC #1 — Implements Saloon Authenticator
it('implements Saloon Authenticator contract', function (): void {
    $store = Mockery::mock(CredentialStoreInterface::class);

    $authenticator = new CloudCodeAuthenticator(
        credentialStore: $store,
        clientId: 'id',
        clientSecret: 'secret',
    );

    expect($authenticator)->toBeInstanceOf(\Saloon\Contracts\Authenticator::class);
});

// AC #2 — Keeps existing refresh token when not returned
it('keeps existing refresh token when not returned in response', function (): void {
    MockClient::global([
        TokenRefreshRequest::class => MockResponse::make([
            'access_token' => 'ya29.new',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            // No refresh_token in response
        ]),
    ]);

    $store = Mockery::mock(CredentialStoreInterface::class);
    $store->shouldReceive('getRefreshToken')->once()->andReturn('1//existing-refresh');
    $store->shouldReceive('updateCredentials')->once()->withArgs(function (string $accessToken, string $refreshToken): bool {
        return $refreshToken === '1//existing-refresh';
    });

    $authenticator = new CloudCodeAuthenticator(
        credentialStore: $store,
        clientId: 'id',
        clientSecret: 'secret',
    );

    $authenticator->refreshToken();
});
