<?php

declare(strict_types=1);

use Ursamajeur\CloudCodePA\Config\ModelRegistry;
use Ursamajeur\CloudCodePA\Exceptions\CloudCodeException;

$defaultModels = [
    'gemini-3.1-pro-high' => 'gemini-3.1-pro-high',
    'gemini-3-pro' => 'gemini-3-pro',
    'gemini-3-flash' => 'gemini-3-flash',
    'gemini-2.5-pro' => 'gemini-2.5-pro',
    'gemini-2.5-flash' => 'gemini-2.5-flash',
    'gemini-2.0-flash' => 'gemini-2.0-flash',
    'gemini-2.0-flash-lite' => 'gemini-2.0-flash-lite',
    'gemini-2.0-flash-thinking' => 'gemini-2.0-flash-thinking',
];

// AC #1 — resolve() returns bare model name for each alias
it('resolves known model alias to bare model name', function () use ($defaultModels): void {
    $registry = new ModelRegistry($defaultModels);

    expect($registry->resolve('gemini-2.0-flash'))->toBe('gemini-2.0-flash');
});

it('resolves all default model aliases correctly', function () use ($defaultModels): void {
    $registry = new ModelRegistry($defaultModels);

    foreach ($defaultModels as $alias => $bareModel) {
        expect($registry->resolve($alias))->toBe($bareModel);
    }
});

// AC #2 — all() returns all configured models
it('lists all available models from config', function () use ($defaultModels): void {
    $registry = new ModelRegistry($defaultModels);

    expect($registry->all())->toBe($defaultModels);
});

// AC #3 — has() reflects config state
it('returns true for known model alias', function () use ($defaultModels): void {
    $registry = new ModelRegistry($defaultModels);

    expect($registry->has('gemini-2.5-pro'))->toBeTrue();
});

it('returns false for unknown model alias', function () use ($defaultModels): void {
    $registry = new ModelRegistry($defaultModels);

    expect($registry->has('gpt-4o'))->toBeFalse();
});

// AC #4 — unknown model throws CloudCodeException listing available models
it('throws CloudCodeException for unknown model with available models listed', function () use ($defaultModels): void {
    $registry = new ModelRegistry($defaultModels);

    expect(fn () => $registry->resolve('unknown-model'))
        ->toThrow(
            CloudCodeException::class,
            'gemini-2.5-pro',
        );
});

it('includes all available models in the exception message', function () use ($defaultModels): void {
    $registry = new ModelRegistry($defaultModels);

    try {
        $registry->resolve('bad-model');
        expect(false)->toBeTrue('Expected exception was not thrown');
    } catch (CloudCodeException $e) {
        foreach (array_keys($defaultModels) as $alias) {
            expect($e->getMessage())->toContain($alias);
        }
    }
});

it('sanitizes control characters in unknown alias exception message', function (): void {
    $registry = new ModelRegistry(['valid' => 'valid']);

    try {
        $registry->resolve("bad\nmodel\x00inject");
        expect(false)->toBeTrue('Expected exception was not thrown');
    } catch (CloudCodeException $e) {
        expect($e->getMessage())
            ->not->toContain("\n")
            ->not->toContain("\x00")
            ->toContain('badmodelinject');
    }
});

// AC #3 — config override changes available models without code changes
it('reflects config changes when instantiated with different models array', function (): void {
    $customModels = [
        'custom-model-v1' => 'custom-model-v1',
        'custom-model-v2' => 'custom-model-v2',
    ];

    $registry = new ModelRegistry($customModels);

    expect($registry->all())->toBe($customModels);
    expect($registry->has('custom-model-v1'))->toBeTrue();
    expect($registry->has('gemini-2.0-flash'))->toBeFalse();
});

// Edge case — empty registry
it('handles empty models array gracefully', function (): void {
    $registry = new ModelRegistry([]);

    expect($registry->all())->toBe([]);
    expect($registry->has('anything'))->toBeFalse();
});

it('throws CloudCodeException on resolve with empty registry', function (): void {
    $registry = new ModelRegistry([]);

    expect(fn () => $registry->resolve('anything'))
        ->toThrow(CloudCodeException::class);
});
