<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA;

use Illuminate\Support\ServiceProvider;
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
     * Publishes the config file. Future stories will add:
     * - Ai::extend('cloudcode-pa', ...) for laravel/ai registration
     * - $this->app['prism-manager']->extend(...) for direct Prism usage
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cloudcode-pa.php' => config_path('cloudcode-pa.php'),
            ], 'cloudcode-pa-config');
        }
    }
}
