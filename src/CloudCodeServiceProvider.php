<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Ai;
use Ursamajeur\CloudCodePA\Auth\CloudCodeAuthenticator;
use Ursamajeur\CloudCodePA\Auth\CredentialStore;
use Ursamajeur\CloudCodePA\Auth\ProjectResolver;
use Ursamajeur\CloudCodePA\Config\CascadeConfig;
use Ursamajeur\CloudCodePA\Config\ModelRegistry;
use Ursamajeur\CloudCodePA\Config\ModelRouter;
use Ursamajeur\CloudCodePA\Contracts\CredentialStoreInterface;
use Ursamajeur\CloudCodePA\Gateway\CloudCodeGateway;
use Ursamajeur\CloudCodePA\Parsing\ChatRequestBuilder;
use Ursamajeur\CloudCodePA\Parsing\ChatResponseMapper;
use Ursamajeur\CloudCodePA\Parsing\RequestBuilder;
use Ursamajeur\CloudCodePA\Parsing\ResponseMapper;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\GeminiCLIConnector;

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

        $this->app->singleton(
            CredentialStoreInterface::class,
            fn () => new CredentialStore(
                (string) config('cloudcode-pa.auth.credentials_path'),
            ),
        );

        $this->app->singleton(
            CloudCodeAuthenticator::class,
            fn () => new CloudCodeAuthenticator(
                credentialStore: $this->app->make(CredentialStoreInterface::class),
                clientId: (string) config('cloudcode-pa.auth.client_id', ''),
                clientSecret: (string) config('cloudcode-pa.auth.client_secret', ''),
                debug: (bool) config('cloudcode-pa.debug', false),
            ),
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

        // Ensure storage directory exists for credential files
        $credentialsPath = (string) config('cloudcode-pa.auth.credentials_path', '');
        $credentialsDir = dirname($credentialsPath);

        if ($credentialsDir !== '' && $credentialsDir !== '.' && ! File::isDirectory($credentialsDir)) {
            File::makeDirectory($credentialsDir, 0700, true, true);
        }

        // Register with laravel/ai SDK
        // Merge package defaults as base so default_model/cheapest_model/smartest_model
        // are always available; ai.php provider overrides take precedence.
        Ai::extend('cloudcode-pa', function ($app, $config): CloudCodeAiProvider {
            $events = $app->make(Dispatcher::class);
            /** @var array<string, mixed> $packageConfig */
            $packageConfig = config('cloudcode-pa', []);

            // Resolve endpoints: env override → config endpoints list
            $baseUrlOverride = (string) config('cloudcode-pa.transport.base_url', '');
            /** @var list<string> $endpoints */
            $endpoints = $baseUrlOverride !== ''
                ? [$baseUrlOverride]
                : (array) config('cloudcode-pa.transport.endpoints', ['https://cloudcode-pa.googleapis.com/v1internal']);

            $connector = new GeminiCLIConnector(
                baseUrl: $endpoints,
                cloudCodeAuth: $app->make(CloudCodeAuthenticator::class),
                timeout: (int) config('cloudcode-pa.transport.timeout', 30),
                connectTimeout: (int) config('cloudcode-pa.transport.connect_timeout', 10),
                debug: (bool) config('cloudcode-pa.debug', false),
            );

            $projectId = (string) config('cloudcode-pa.project', '');
            $projectResolver = null;

            if ($projectId === '') {
                $projectResolver = new ProjectResolver(
                    connector: $connector,
                    debug: (bool) config('cloudcode-pa.debug', false),
                );
            }

            $requestBuilder = new RequestBuilder(
                modelRegistry: $app->make(ModelRegistry::class),
                project: $projectId,
                projectResolver: $projectResolver,
            );

            $chatRequestBuilder = new ChatRequestBuilder(
                project: $projectId,
                projectResolver: $projectResolver,
            );

            $defaultModel = (string) config('cloudcode-pa.default_model', 'claude-opus-4');
            /** @var list<string> $cascadeSteps */
            $cascadeSteps = (array) config('cloudcode-pa.cascade.steps', []);

            $cascadeConfig = new CascadeConfig(
                enabled: (bool) config('cloudcode-pa.cascade.enabled', true),
                steps: $cascadeSteps,
                triggerModel: $defaultModel,
            );

            $gateway = new CloudCodeGateway(
                connector: $connector,
                requestBuilder: $requestBuilder,
                responseMapper: new ResponseMapper,
                modelRouter: new ModelRouter,
                chatRequestBuilder: $chatRequestBuilder,
                chatResponseMapper: new ChatResponseMapper,
                cascadeConfig: $cascadeConfig,
                streamTimeout: (int) config('cloudcode-pa.transport.stream_timeout', 120),
            );

            return new CloudCodeAiProvider(
                gateway: $gateway,
                config: array_merge($packageConfig, $config),
                events: $events,
            );
        });
    }
}
