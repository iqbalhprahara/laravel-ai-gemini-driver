<?php

declare(strict_types=1);

use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\Prism\PrismGateway;
use Prism\Prism\PrismManager;
use Prism\Prism\Text\Request as TextRequest;
use Ursamajeur\CloudCodePA\CloudCodeAiProvider;
use Ursamajeur\CloudCodePA\CloudCodePrismProvider;

// AC #1 — CloudCodeAiProvider is registered as the laravel/ai provider
it('Ai::extend cloudcode-pa resolves to CloudCodeAiProvider', function (): void {
    // Arrange & Act
    $provider = Ai::textProvider('cloudcode-pa');

    // Assert
    expect($provider)->toBeInstanceOf(CloudCodeAiProvider::class);
});

// AC #2 — CloudCodePrismProvider is registered via prism-manager
it('prism-manager resolves cloudcode-pa to CloudCodePrismProvider', function (): void {
    // Arrange & Act
    $provider = $this->app->make(PrismManager::class)->resolve('cloudcode-pa');

    // Assert
    expect($provider)->toBeInstanceOf(CloudCodePrismProvider::class);
});

// AC #3 — Both providers resolve correctly from the service container
it('both providers instantiate without errors when config is present', function (): void {
    // Arrange & Act
    $aiProvider = Ai::textProvider('cloudcode-pa');
    $prismProvider = $this->app->make(PrismManager::class)->resolve('cloudcode-pa');

    // Assert
    expect($aiProvider)
        ->toBeInstanceOf(CloudCodeAiProvider::class)
        ->toBeInstanceOf(TextProvider::class)
        ->and($prismProvider)
        ->toBeInstanceOf(CloudCodePrismProvider::class);
});

// AC #4 — Two-layer integration: CloudCodeAiProvider uses PrismGateway (canonical pattern)
it('CloudCodeAiProvider uses PrismGateway as its text gateway', function (): void {
    // Arrange & Act
    $provider = Ai::textProvider('cloudcode-pa');

    // Assert — confirms canonical two-layer pattern: AiProvider → PrismGateway → PrismProvider
    expect($provider)
        ->toBeInstanceOf(TextProvider::class)
        ->and($provider->textGateway())
        ->toBeInstanceOf(PrismGateway::class);
});

// Stub behavior — CloudCodePrismProvider::text() throws until Epic 3
it('CloudCodePrismProvider text() throws BadMethodCallException', function (): void {
    // Arrange
    $provider = $this->app->make(PrismManager::class)->resolve('cloudcode-pa');
    $request = Mockery::mock(TextRequest::class);

    // Act & Assert
    expect(fn () => $provider->text($request))
        ->toThrow(\BadMethodCallException::class, 'Not yet implemented');
});

// Stub behavior — CloudCodePrismProvider::stream() throws until Epic 3
it('CloudCodePrismProvider stream() throws BadMethodCallException', function (): void {
    // Arrange
    $provider = $this->app->make(PrismManager::class)->resolve('cloudcode-pa');
    $request = Mockery::mock(TextRequest::class);

    // Act & Assert
    expect(fn () => $provider->stream($request))
        ->toThrow(\BadMethodCallException::class, 'Not yet implemented');
});
