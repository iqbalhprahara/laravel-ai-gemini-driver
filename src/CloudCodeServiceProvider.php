<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Ai;
use Laravel\Ai\Gateway\Prism\PrismGateway;
use Prism\Prism\PrismManager;
use Ursamajeur\CloudCodePA\Config\ModelRegistry;

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
     * Registers 'cloudcode-pa' with laravel/ai and Prism so consumers can use:
     *   Ai::agent()->using('cloudcode-pa', ...)   — via CloudCodeAiProvider
     *   Prism::text()->using('cloudcode-pa', ...) — via CloudCodePrismProvider
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cloudcode-pa.php' => config_path('cloudcode-pa.php'),
            ], 'cloudcode-pa-config');
        }

        // Register with laravel/ai SDK (AC #1)
        Ai::extend('cloudcode-pa', function ($app, $config): CloudCodeAiProvider {
            $events = $app->make(Dispatcher::class);

            return new CloudCodeAiProvider(
                gateway: new PrismGateway($events),
                config: $config,
                events: $events,
            );
        });

        // Register with Prism directly (AC #2)
        $this->app->make(PrismManager::class)->extend(
            'cloudcode-pa',
            fn ($app, $config): CloudCodePrismProvider => new CloudCodePrismProvider(
                config: $config,
            ),
        );
    }
}
