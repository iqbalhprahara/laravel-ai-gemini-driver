<?php

declare(strict_types=1);

use Ursamajeur\CloudCodePA\Exceptions\CloudCodeException;

// AC #1 — Inheritance
it('extends RuntimeException', function (): void {
    $exception = CloudCodeException::unknownModel('bad', ['good']);

    expect($exception)->toBeInstanceOf(\RuntimeException::class);
});

// unknownModel factory
it('creates exception via unknownModel factory with available models listed', function (): void {
    $exception = CloudCodeException::unknownModel('bad-model', ['gemini-2.0-flash', 'gemini-3-pro']);

    expect($exception)
        ->toBeInstanceOf(CloudCodeException::class)
        ->and($exception->getMessage())->toContain('bad-model')
        ->and($exception->getMessage())->toContain('gemini-2.0-flash')
        ->and($exception->getMessage())->toContain('gemini-3-pro');
});

it('sanitizes control characters in unknownModel alias', function (): void {
    $exception = CloudCodeException::unknownModel("evil\x00\ninput", ['valid']);

    expect($exception->getMessage())
        ->not->toContain("\x00")
        ->not->toContain("\n")
        ->toContain('evilinput');
});

// notImplemented factory
it('creates exception via notImplemented factory with method name', function (): void {
    $exception = CloudCodeException::notImplemented('text()');

    expect($exception)
        ->toBeInstanceOf(CloudCodeException::class)
        ->and($exception->getMessage())->toContain('text()')
        ->and($exception->getMessage())->toContain('not yet implemented');
});
