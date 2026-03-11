<?php

declare(strict_types=1);

use Ursamajeur\CloudCodePA\Config\CascadeConfig;

it('cascades when enabled and model matches trigger', function (): void {
    $config = new CascadeConfig(
        enabled: true,
        steps: ['claude-opus-4', 'gemini-2.5-flash'],
        triggerModel: 'claude-opus-4',
    );

    expect($config->shouldCascade('claude-opus-4'))->toBeTrue();
});

it('does not cascade when disabled', function (): void {
    $config = new CascadeConfig(
        enabled: false,
        steps: ['claude-opus-4', 'gemini-2.5-flash'],
        triggerModel: 'claude-opus-4',
    );

    expect($config->shouldCascade('claude-opus-4'))->toBeFalse();
});

it('does not cascade when model does not match trigger', function (): void {
    $config = new CascadeConfig(
        enabled: true,
        steps: ['claude-opus-4', 'gemini-2.5-flash'],
        triggerModel: 'claude-opus-4',
    );

    expect($config->shouldCascade('gemini-2.5-flash'))->toBeFalse();
});

it('does not cascade when steps are empty', function (): void {
    $config = new CascadeConfig(
        enabled: true,
        steps: [],
        triggerModel: 'claude-opus-4',
    );

    expect($config->shouldCascade('claude-opus-4'))->toBeFalse();
});

it('exposes configuration as public properties', function (): void {
    $config = new CascadeConfig(
        enabled: true,
        steps: ['model-a', 'model-b'],
        triggerModel: 'model-a',
    );

    expect($config->enabled)->toBeTrue()
        ->and($config->steps)->toBe(['model-a', 'model-b'])
        ->and($config->triggerModel)->toBe('model-a');
});
