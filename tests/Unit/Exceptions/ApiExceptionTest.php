<?php

declare(strict_types=1);

use Ursamajeur\CloudCodePA\Exceptions\ApiException;
use Ursamajeur\CloudCodePA\Exceptions\CloudCodeException;

// AC #1 — Inheritance
it('extends CloudCodeException', function (): void {
    $exception = ApiException::serverError(500, 'Internal Server Error');

    expect($exception)->toBeInstanceOf(CloudCodeException::class)
        ->and($exception)->toBeInstanceOf(\RuntimeException::class);
});

// AC #5 — rateLimited factory
it('creates exception via rateLimited factory with model name', function (): void {
    $exception = ApiException::rateLimited('gemini-2.5-pro');

    expect($exception)
        ->toBeInstanceOf(ApiException::class)
        ->and($exception->getMessage())->toContain('gemini-2.5-pro')
        ->and($exception->getMessage())->toContain('rate');
});

it('creates exception via rateLimited factory without model name', function (): void {
    $exception = ApiException::rateLimited();

    expect($exception)->toBeInstanceOf(ApiException::class)
        ->and($exception->statusCode)->toBe(429);
});

// AC #5 — serverError factory
it('creates exception via serverError factory', function (): void {
    $exception = ApiException::serverError(503, 'Service Unavailable', 'gemini-2.0-flash');

    expect($exception)
        ->toBeInstanceOf(ApiException::class)
        ->and($exception->getMessage())->toContain('503')
        ->and($exception->getMessage())->toContain('Service Unavailable')
        ->and($exception->getMessage())->toContain('gemini-2.0-flash');
});

// AC #5 — clientError factory
it('creates exception via clientError factory', function (): void {
    $exception = ApiException::clientError(400, 'Bad Request', 'gemini-2.5-pro');

    expect($exception)
        ->toBeInstanceOf(ApiException::class)
        ->and($exception->getMessage())->toContain('400')
        ->and($exception->getMessage())->toContain('Bad Request');
});

// AC #5 — readonly properties stored correctly
it('stores statusCode errorMessage and model as readonly properties', function (): void {
    $exception = ApiException::serverError(502, 'Bad Gateway', 'gemini-3-flash');

    expect($exception->statusCode)->toBe(502)
        ->and($exception->errorMessage)->toBe('Bad Gateway')
        ->and($exception->model)->toBe('gemini-3-flash');
});

it('stores null model when not provided', function (): void {
    $exception = ApiException::serverError(500, 'Internal Server Error');

    expect($exception->model)->toBeNull();
});

// AC #4 — No tokens in messages
it('never includes bearer tokens in messages', function (): void {
    $exceptions = [
        ApiException::rateLimited('gemini-2.5-pro'),
        ApiException::serverError(500, 'Internal Server Error'),
        ApiException::clientError(400, 'Bad Request'),
    ];

    foreach ($exceptions as $exception) {
        expect($exception->getMessage())
            ->not->toContain('Bearer')
            ->not->toContain('access_token');
    }
});
