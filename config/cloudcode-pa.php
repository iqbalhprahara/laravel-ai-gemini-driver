<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Configuration for OAuth2 credential management. The credentials_path
    | points to the JSON file containing OAuth tokens — defaults to the same
    | location Gemini CLI uses (~/.gemini/oauth_creds.json).
    |
    | client_id and client_secret are Google's CLI OAuth credentials used for
    | the PKCE authorization flow when acquiring fresh tokens.
    |
    */

    'auth' => [
        // Path to the OAuth credentials JSON file
        'credentials_path' => env(
            'CLOUDCODE_PA_CREDENTIALS_PATH',
            rtrim((string) (getenv('HOME') ?: ($_SERVER['HOME'] ?? '~')), '/').'/.gemini/oauth_creds.json',
        ),

        // Google OAuth client ID for PKCE flow
        'client_id' => env('CLOUDCODE_PA_CLIENT_ID', ''),

        // Google OAuth client secret for PKCE flow
        'client_secret' => env('CLOUDCODE_PA_CLIENT_SECRET', ''),

        // OAuth redirect URI — localhost for CLI-based auth
        'redirect_uri' => env('CLOUDCODE_PA_REDIRECT_URI', 'http://localhost'),
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
