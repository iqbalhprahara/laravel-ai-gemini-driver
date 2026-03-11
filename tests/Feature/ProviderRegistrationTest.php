<?php

declare(strict_types=1);

use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\Gateway\TextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Ursamajeur\CloudCodePA\CloudCodeAiProvider;
use Ursamajeur\CloudCodePA\Gateway\CloudCodeGateway;

// AC #1 — CloudCodeAiProvider is registered as the laravel/ai provider
it('Ai::extend cloudcode-pa resolves to CloudCodeAiProvider', function (): void {
    // Arrange & Act
    $provider = Ai::textProvider('cloudcode-pa');

    // Assert
    expect($provider)->toBeInstanceOf(CloudCodeAiProvider::class);
});

// AC #2 — Provider implements TextProvider contract
it('CloudCodeAiProvider implements TextProvider', function (): void {
    // Arrange & Act
    $provider = Ai::textProvider('cloudcode-pa');

    // Assert
    expect($provider)->toBeInstanceOf(TextProvider::class);
});

// AC #3 — Direct gateway pattern: CloudCodeAiProvider uses CloudCodeGateway
it('CloudCodeAiProvider uses CloudCodeGateway as its text gateway', function (): void {
    // Arrange & Act
    $provider = Ai::textProvider('cloudcode-pa');

    // Assert — confirms direct gateway pattern: AiProvider → CloudCodeGateway → Saloon
    expect($provider->textGateway())
        ->toBeInstanceOf(TextGateway::class)
        ->toBeInstanceOf(CloudCodeGateway::class);
});

// Gateway obtained from provider has correct type
it('CloudCodeGateway is accessible from resolved provider', function (): void {
    $provider = Ai::textProvider('cloudcode-pa');
    $gateway = $provider->textGateway();

    expect($gateway)->toBeInstanceOf(CloudCodeGateway::class);
});

// Model method tests — defaults from package config
it('CloudCodeAiProvider defaultTextModel returns config default', function (): void {
    // Arrange & Act
    $provider = Ai::textProvider('cloudcode-pa');

    // Assert — value from config/cloudcode-pa.php default_model key
    expect($provider->defaultTextModel())->toBe('claude-opus-4');
});

it('CloudCodeAiProvider cheapestTextModel returns config default', function (): void {
    // Arrange & Act
    $provider = Ai::textProvider('cloudcode-pa');

    // Assert
    expect($provider->cheapestTextModel())->toBe('gemini-2.5-flash');
});

it('CloudCodeAiProvider smartestTextModel returns config default', function (): void {
    // Arrange & Act
    $provider = Ai::textProvider('cloudcode-pa');

    // Assert
    expect($provider->smartestTextModel())->toBe('claude-opus-4');
});

it('CloudCodeAiProvider model methods reflect config overrides', function (): void {
    // Arrange
    config()->set('cloudcode-pa.default_model', 'gemini-3-flash');
    config()->set('cloudcode-pa.cheapest_model', 'gemini-2.0-flash');
    config()->set('cloudcode-pa.smartest_model', 'gemini-3-pro');

    // Act — re-resolve provider so it picks up new config
    $provider = Ai::textProvider('cloudcode-pa');

    // Assert
    expect($provider->defaultTextModel())->toBe('gemini-3-flash')
        ->and($provider->cheapestTextModel())->toBe('gemini-2.0-flash')
        ->and($provider->smartestTextModel())->toBe('gemini-3-pro');
});
