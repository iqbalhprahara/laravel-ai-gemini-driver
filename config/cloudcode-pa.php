<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Configuration for OAuth2 credential management. The credentials_path
    | points to the JSON file containing OAuth tokens — defaults to
    | storage/cloudcode-pa/oauth_creds.json (project-local).
    |
    | client_id and client_secret are Google's PUBLIC CLI OAuth credentials
    | (same as Gemini CLI). Used by CloudCodeAuthenticator for token refresh.
    | These are NOT user secrets — they are embedded in Google's CLI source.
    |
    */

    'auth' => [
        // Path to the OAuth credentials JSON file (project-local)
        'credentials_path' => env(
            'CLOUDCODE_PA_CREDENTIALS_PATH',
            storage_path('cloudcode-pa/oauth_creds.json'),
        ),

        // Google's public CLI OAuth client ID (from @google/gemini-cli-core)
        'client_id' => env('CLOUDCODE_PA_CLIENT_ID', ''),

        // Google's public CLI OAuth client secret (from @google/gemini-cli-core)
        'client_secret' => env('CLOUDCODE_PA_CLIENT_SECRET', ''),

    ],

    /*
    |--------------------------------------------------------------------------
    | Transport
    |--------------------------------------------------------------------------
    |
    | HTTP transport settings for the CloudCode-PA v1internal API.
    | Streaming requests use a separate, longer timeout since responses
    | are delivered incrementally over SSE.
    |
    */

    'transport' => [
        // Base URL for the v1internal API gateway
        'base_url' => env('CLOUDCODE_PA_BASE_URL', 'https://cloudcode-pa.googleapis.com/v1internal'),

        // Request timeout in seconds for non-streaming requests
        'timeout' => (int) env('CLOUDCODE_PA_TIMEOUT', 30),

        // Timeout in seconds for streaming (SSE) requests
        'stream_timeout' => (int) env('CLOUDCODE_PA_STREAM_TIMEOUT', 120),

        // Connection timeout in seconds
        'connect_timeout' => (int) env('CLOUDCODE_PA_CONNECT_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Model registry mapping friendly aliases to bare v1internal model names.
    | Add new models here — no code changes required. The ModelRegistry
    | singleton reads this array at boot and resolves aliases at runtime.
    |
    */

    // Default model aliases for laravel/ai Provider methods
    'default_model' => env('CLOUDCODE_PA_DEFAULT_MODEL', 'gemini-2.0-flash'),
    'cheapest_model' => env('CLOUDCODE_PA_CHEAPEST_MODEL', 'gemini-2.0-flash-lite'),
    'smartest_model' => env('CLOUDCODE_PA_SMARTEST_MODEL', 'gemini-3.1-pro-high'),

    'models' => [
        // Gemini 3.x — latest generation (GeminiCLI path)
        'gemini-3.1-pro-high' => 'gemini-3.1-pro-high',
        'gemini-3-pro' => 'gemini-3-pro',
        'gemini-3-flash' => 'gemini-3-flash',

        // Gemini 2.5 — previous generation
        'gemini-2.5-pro' => 'gemini-2.5-pro',
        'gemini-2.5-flash' => 'gemini-2.5-flash',

        // Gemini 2.0 — stable workhorses
        'gemini-2.0-flash' => 'gemini-2.0-flash',
        'gemini-2.0-flash-lite' => 'gemini-2.0-flash-lite',
        'gemini-2.0-flash-thinking' => 'gemini-2.0-flash-thinking',
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug
    |--------------------------------------------------------------------------
    |
    | When enabled, logs request/response details via Saloon middleware.
    | Credentials are always redacted regardless of this setting.
    |
    */

    // Enable debug logging for API requests
    'debug' => (bool) env('CLOUDCODE_PA_DEBUG', false),

];
