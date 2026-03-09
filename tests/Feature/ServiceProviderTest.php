<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Ursamajeur\CloudCodePA\CloudCodeServiceProvider;
use Ursamajeur\CloudCodePA\Config\ModelRegistry;

it('registers the service provider via auto-discovery config', function (): void {
    // Arrange & Act — provider is loaded by TestCase::getPackageProviders()
    $providers = $this->app->getLoadedProviders();

    // Assert
    expect($providers)->toHaveKey(CloudCodeServiceProvider::class);
});

it('merges config from package config file', function (): void {
    // Assert — config is merged during register()
    expect(config('cloudcode-pa'))->toBeArray()
        ->and(config('cloudcode-pa'))->toHaveKeys(['auth', 'transport', 'models', 'debug']);
});

it('publishes config with the correct tag', function (): void {
    // Arrange
    $publishable = ServiceProvider::pathsToPublish(
        CloudCodeServiceProvider::class,
        'cloudcode-pa-config',
    );

    // Assert
    expect($publishable)->toBeArray()
        ->and($publishable)->toHaveCount(1)
        ->and(array_values($publishable)[0])->toEndWith('config/cloudcode-pa.php');
});

it('config contains auth group with expected keys', function (): void {
    expect(config('cloudcode-pa.auth'))
        ->toBeArray()
        ->toHaveKeys(['credentials_path', 'client_id', 'client_secret', 'redirect_uri']);
});

it('config contains transport group with expected keys', function (): void {
    expect(config('cloudcode-pa.transport'))
        ->toBeArray()
        ->toHaveKeys(['base_url', 'timeout', 'stream_timeout', 'connect_timeout']);
});

it('config contains models group as array', function (): void {
    expect(config('cloudcode-pa.models'))->toBeArray();
});

it('config contains model alias keys with string defaults', function (): void {
    expect(config('cloudcode-pa.default_model'))->toBeString()
        ->and(config('cloudcode-pa.cheapest_model'))->toBeString()
        ->and(config('cloudcode-pa.smartest_model'))->toBeString();
});

it('config debug key defaults to false', function (): void {
    expect(config('cloudcode-pa.debug'))->toBeFalse();
});

it('provides sensible default values for all config keys', function (): void {
    $home = rtrim((string) (getenv('HOME') ?: ($_SERVER['HOME'] ?? '~')), '/');

    expect(config('cloudcode-pa.auth.credentials_path'))->toBe($home.'/.gemini/oauth_creds.json')
        ->and(config('cloudcode-pa.auth.client_id'))->toBe('')
        ->and(config('cloudcode-pa.auth.client_secret'))->toBe('')
        ->and(config('cloudcode-pa.auth.redirect_uri'))->toBe('http://localhost')
        ->and(config('cloudcode-pa.transport.base_url'))->toBe('https://cloudcode-pa.googleapis.com/v1internal')
        ->and(config('cloudcode-pa.transport.timeout'))->toBe(30)
        ->and(config('cloudcode-pa.transport.stream_timeout'))->toBe(120)
        ->and(config('cloudcode-pa.transport.connect_timeout'))->toBe(10)
        ->and(config('cloudcode-pa.default_model'))->toBe('gemini-2.0-flash')
        ->and(config('cloudcode-pa.cheapest_model'))->toBe('gemini-2.0-flash-lite')
        ->and(config('cloudcode-pa.smartest_model'))->toBe('gemini-3.1-pro-high')
        ->and(config('cloudcode-pa.debug'))->toBeFalse();
});

it('config values can be overridden at runtime', function (): void {
    // Arrange
    config()->set('cloudcode-pa.auth.credentials_path', '/custom/path/creds.json');
    config()->set('cloudcode-pa.transport.timeout', 60);
    config()->set('cloudcode-pa.debug', true);

    // Assert
    expect(config('cloudcode-pa.auth.credentials_path'))->toBe('/custom/path/creds.json')
        ->and(config('cloudcode-pa.transport.timeout'))->toBe(60)
        ->and(config('cloudcode-pa.debug'))->toBeTrue();
});

it('transport timeout values are integers', function (): void {
    expect(config('cloudcode-pa.transport.timeout'))->toBeInt()
        ->and(config('cloudcode-pa.transport.stream_timeout'))->toBeInt()
        ->and(config('cloudcode-pa.transport.connect_timeout'))->toBeInt();
});

it('resolves ModelRegistry singleton from container with config models', function (): void {
    $registry = $this->app->make(ModelRegistry::class);

    expect($registry)->toBeInstanceOf(ModelRegistry::class)
        ->and($registry->has('gemini-2.0-flash'))->toBeTrue()
        ->and($registry->all())->toBe(config('cloudcode-pa.models'));
});

it('returns same ModelRegistry instance on repeated resolution', function (): void {
    $a = $this->app->make(ModelRegistry::class);
    $b = $this->app->make(ModelRegistry::class);

    expect($a)->toBe($b);
});

it('all config keys are accessible via dot notation', function (): void {
    $keys = [
        'cloudcode-pa.auth.credentials_path',
        'cloudcode-pa.auth.client_id',
        'cloudcode-pa.auth.client_secret',
        'cloudcode-pa.auth.redirect_uri',
        'cloudcode-pa.transport.base_url',
        'cloudcode-pa.transport.timeout',
        'cloudcode-pa.transport.stream_timeout',
        'cloudcode-pa.transport.connect_timeout',
        'cloudcode-pa.default_model',
        'cloudcode-pa.cheapest_model',
        'cloudcode-pa.smartest_model',
        'cloudcode-pa.models',
        'cloudcode-pa.debug',
    ];

    foreach ($keys as $key) {
        expect(config($key))->not->toBeNull("Config key '{$key}' should not be null");
    }
});
