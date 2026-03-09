<?php

declare(strict_types=1);

use Ursamajeur\CloudCodePA\Exceptions\AuthenticationException;
use Ursamajeur\CloudCodePA\Exceptions\CloudCodeException;

// AC #1 — Inheritance
it('extends CloudCodeException', function (): void {
    $exception = AuthenticationException::tokenExpired();

    expect($exception)->toBeInstanceOf(CloudCodeException::class)
        ->and($exception)->toBeInstanceOf(\RuntimeException::class);
});

// AC #2, #3 — tokenExpired factory
it('creates exception via tokenExpired factory with re-auth instructions', function (): void {
    $exception = AuthenticationException::tokenExpired();

    expect($exception)
        ->toBeInstanceOf(AuthenticationException::class)
        ->and($exception->getMessage())->toContain('expired')
        ->and($exception->getMessage())->toContain('PKCE');
});

// AC #3 — credentialsNotFound factory
it('creates exception via credentialsNotFound factory with path', function (): void {
    $path = '/home/user/.config/cloudcode/creds.json';
    $exception = AuthenticationException::credentialsNotFound($path);

    expect($exception)
        ->toBeInstanceOf(AuthenticationException::class)
        ->and($exception->getMessage())->toContain($path)
        ->and($exception->getMessage())->toContain('PKCE');
});

// AC #3 — refreshFailed factory
it('creates exception via refreshFailed factory', function (): void {
    $exception = AuthenticationException::refreshFailed('token revoked');

    expect($exception)
        ->toBeInstanceOf(AuthenticationException::class)
        ->and($exception->getMessage())->toContain('token revoked')
        ->and($exception->getMessage())->toContain('PKCE');
});

it('creates exception via refreshFailed factory without reason', function (): void {
    $exception = AuthenticationException::refreshFailed();

    expect($exception)
        ->toBeInstanceOf(AuthenticationException::class)
        ->and($exception->getMessage())->toContain('PKCE');
});

// AC #3 — invalidCredentials factory
it('creates exception via invalidCredentials factory', function (): void {
    $exception = AuthenticationException::invalidCredentials();

    expect($exception)
        ->toBeInstanceOf(AuthenticationException::class)
        ->and($exception->getMessage())->toContain('PKCE');
});

// AC #4 — No tokens in messages
it('never includes bearer tokens or raw credentials in messages', function (): void {
    $exceptions = [
        AuthenticationException::tokenExpired(),
        AuthenticationException::credentialsNotFound('/some/path'),
        AuthenticationException::refreshFailed('some reason'),
        AuthenticationException::invalidCredentials(),
    ];

    foreach ($exceptions as $exception) {
        expect($exception->getMessage())
            ->not->toContain('Bearer')
            ->not->toContain('eyJ')
            ->not->toContain('access_token')
            ->not->toContain('refresh_token');
    }
});
