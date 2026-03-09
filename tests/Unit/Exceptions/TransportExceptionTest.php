<?php

declare(strict_types=1);

use Ursamajeur\CloudCodePA\Exceptions\CloudCodeException;
use Ursamajeur\CloudCodePA\Exceptions\TransportException;

// AC #1 — Inheritance
it('extends CloudCodeException', function (): void {
    $exception = TransportException::connectionFailed();

    expect($exception)->toBeInstanceOf(CloudCodeException::class)
        ->and($exception)->toBeInstanceOf(\RuntimeException::class);
});

// AC #5 — connectionFailed factory
it('creates exception via connectionFailed factory', function (): void {
    $exception = TransportException::connectionFailed();

    expect($exception)
        ->toBeInstanceOf(TransportException::class)
        ->and($exception->getMessage())->toContain('connection');
});

it('creates exception via connectionFailed factory wrapping previous exception', function (): void {
    $previous = new \RuntimeException('DNS resolution failed');
    $exception = TransportException::connectionFailed($previous);

    expect($exception)
        ->toBeInstanceOf(TransportException::class)
        ->and($exception->getPrevious())->toBe($previous);
});

// AC #5 — timeout factory
it('creates exception via timeout factory', function (): void {
    $exception = TransportException::timeout();

    expect($exception)
        ->toBeInstanceOf(TransportException::class)
        ->and($exception->getMessage())->toContain('timeout');
});

it('creates exception via timeout factory wrapping previous exception', function (): void {
    $previous = new \RuntimeException('Connection timed out after 30000ms');
    $exception = TransportException::timeout($previous);

    expect($exception)
        ->toBeInstanceOf(TransportException::class)
        ->and($exception->getPrevious())->toBe($previous);
});

// AC #4 — No tokens in messages
it('never includes bearer tokens in messages', function (): void {
    $exceptions = [
        TransportException::connectionFailed(),
        TransportException::timeout(),
    ];

    foreach ($exceptions as $exception) {
        expect($exception->getMessage())
            ->not->toContain('Bearer')
            ->not->toContain('access_token');
    }
});
