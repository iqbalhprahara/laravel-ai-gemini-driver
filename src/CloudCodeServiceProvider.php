<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Ai;
use Ursamajeur\CloudCodePA\Config\ModelRegistry;
use Ursamajeur\CloudCodePA\Gateway\CloudCodeGateway;

final class CloudCodeServiceProvider extends ServiceProvider
{
    /**
     * Register package services and merge configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/cloudcode-pa.php',
            'cloudcode-pa',
        );

        $this->app->singleton(
            ModelRegistry::class,
            fn () => new ModelRegistry(config('cloudcode-pa.models', [])),
        );
    }

    /**
     * Bootstrap package services.
     *
     * Registers 'cloudcode-pa' with laravel/ai so consumers can use:
     *   Ai::agent()->using('cloudcode-pa', ...)   — via CloudCodeAiProvider
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cloudcode-pa.php' => config_path('cloudcode-pa.php'),
            ], 'cloudcode-pa-config');
        }

        // Register with laravel/ai SDK
        // Merge package defaults as base so default_model/cheapest_model/smartest_model
        // are always available; ai.php provider overrides take precedence.
        Ai::extend('cloudcode-pa', function ($app, $config): CloudCodeAiProvider {
            $events = $app->make(Dispatcher::class);
            /** @var array<string, mixed> $packageConfig */
            $packageConfig = config('cloudcode-pa', []);

            return new CloudCodeAiProvider(
                gateway: new CloudCodeGateway,
                config: array_merge($packageConfig, $config),
                events: $events,
            );
        });
    }
}
