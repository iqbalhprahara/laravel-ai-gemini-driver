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
    | Project
    |--------------------------------------------------------------------------
    |
    | The CloudCode-PA companion project ID, obtained during Gemini CLI
    | onboarding via the loadCodeAssist RPC. This is required for all
    | generateContent requests. If not set, the package will call
    | loadCodeAssist automatically to discover the project ID.
    |
    */

    'project' => env('CLOUDCODE_PA_PROJECT', ''),

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
        // Ordered list of v1internal endpoints for fallback routing.
        // On 429 (rate limited), the next endpoint is tried automatically.
        // Each domain has an independent quota pool.
        'endpoints' => [
            'https://daily-cloudcode-pa.googleapis.com/v1internal',   // Antigravity — primary
            'https://cloudcode-pa.googleapis.com/v1internal',          // Gemini CLI — fallback
        ],

        // Legacy single URL (overrides endpoints if set)
        'base_url' => env('CLOUDCODE_PA_BASE_URL', ''),

        // Request timeout in seconds for non-streaming requests
        'timeout' => (int) env('CLOUDCODE_PA_TIMEOUT', 30),

        // Timeout in seconds for streaming (SSE) requests
        'stream_timeout' => (int) env('CLOUDCODE_PA_STREAM_TIMEOUT', 120),

        // Connection timeout in seconds
        'connect_timeout' => (int) env('CLOUDCODE_PA_CONNECT_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cascade
    |--------------------------------------------------------------------------
    |
    | When enabled, the gateway cascades through the steps list on 429 (rate
    | limited). Each step tries all configured endpoints before advancing to
    | the next model. Only activates when the requested model matches the
    | default_model — explicit model requests use endpoint fallback only.
    |
    */

    'cascade' => [
        'enabled' => (bool) env('CLOUDCODE_PA_CASCADE_ENABLED', true),
        'steps' => [
            'claude-opus-4',          // Partner — best quality (generateChat)
            'gemini-3-pro-preview',   // Gemini 3 — bleeding edge (generateContent)
            'gemini-3-flash-preview', // Gemini 3 — fast bleeding edge (generateContent)
            'gemini-2.5-pro',         // Gemini 2.5 — stable high quality (generateContent)
            'gemini-2.5-flash',       // Gemini 2.5 — stable fast fallback (generateContent)
        ],
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
    | Partner models (claude-*, gpt-*) are routed to generateChat automatically.
    | Gemini models are routed to generateContent.
    |
    */

    // Default model aliases for laravel/ai Provider methods
    'default_model' => env('CLOUDCODE_PA_DEFAULT_MODEL', 'claude-opus-4'),
    'cheapest_model' => env('CLOUDCODE_PA_CHEAPEST_MODEL', 'gemini-2.5-flash'),
    'smartest_model' => env('CLOUDCODE_PA_SMARTEST_MODEL', 'claude-opus-4'),

    'models' => [
        // Partner models — routed via generateChat + model_config_id
        'claude-opus-4' => 'claude-opus-4',
        'claude-sonnet-4' => 'claude-sonnet-4',
        'claude-sonnet-4-5' => 'claude-sonnet-4-5',
        'claude-haiku-4-5' => 'claude-haiku-4-5',
        'gpt-oss-120b-medium' => 'gpt-oss-120b-medium',
        'gpt-4-1' => 'gpt-4-1',

        // Gemini 3.x — preview generation
        'gemini-3-pro-preview' => 'gemini-3-pro-preview',
        'gemini-3-flash-preview' => 'gemini-3-flash-preview',

        // Gemini 2.5 — current stable generation
        'gemini-2.5-pro' => 'gemini-2.5-pro',
        'gemini-2.5-flash' => 'gemini-2.5-flash',
        'gemini-2.5-flash-lite' => 'gemini-2.5-flash-lite',

        // Gemini 2.0 — legacy (still available on v1internal)
        'gemini-2.0-flash' => 'gemini-2.0-flash',
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
