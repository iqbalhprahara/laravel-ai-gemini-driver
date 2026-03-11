<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Transport\GeminiCLI;

use Illuminate\Support\Facades\Log;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Ursamajeur\CloudCodePA\Auth\CloudCodeAuthenticator;

final class GeminiCLIConnector extends Connector
{
    /** @var list<string> */
    private array $endpoints;

    private int $activeEndpointIndex = 0;

    /**
     * @param  string|list<string>  $baseUrl  Single URL or ordered list for fallback
     */
    public function __construct(
        string|array $baseUrl,
        private readonly CloudCodeAuthenticator $cloudCodeAuth,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
        private readonly bool $debug = false,
    ) {
        $this->endpoints = is_array($baseUrl) ? array_values($baseUrl) : [$baseUrl];
    }

    public function resolveBaseUrl(): string
    {
        return $this->endpoints[$this->activeEndpointIndex];
    }

    /**
     * Send a request with automatic fallback to alternate endpoints on 429.
     *
     * Tries each endpoint in order. On 429 (rate limited), rotates to the
     * next endpoint. Returns the first successful response, or the last
     * failure if all endpoints are exhausted.
     */
    public function sendWithFallback(Request $request): Response
    {
        $lastResponse = null;

        for ($i = 0; $i < count($this->endpoints); $i++) {
            $this->activeEndpointIndex = $i;

            $response = $this->send($request);

            if ($response->status() !== 429) {
                return $response;
            }

            $lastResponse = $response;

            if ($this->debug && $i < count($this->endpoints) - 1) {
                Log::debug('CloudCode-PA: Rate limited, falling back to next endpoint', [
                    'exhausted' => $this->endpoints[$i],
                    'next' => $this->endpoints[$i + 1],
                ]);
            }
        }

        // Reset to first endpoint for next call
        $this->activeEndpointIndex = 0;

        /** @var Response $lastResponse */
        return $lastResponse;
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
        ];
    }

    protected function defaultAuth(): Authenticator
    {
        return $this->cloudCodeAuth;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
        ];
    }

    public function boot(PendingRequest $pendingRequest): void
    {
        if (! $this->debug) {
            return;
        }

        $pendingRequest->middleware()->onRequest(function (PendingRequest $request): void {
            Log::debug('CloudCode-PA Request', [
                'method' => $request->getMethod()->value,
                'url' => $request->getUrl(),
                'headers' => $this->redactHeaders($request->headers()->all()),
            ]);
        });

        $pendingRequest->middleware()->onResponse(function (Response $response): void {
            Log::debug('CloudCode-PA Response', [
                'status' => $response->status(),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    private function redactHeaders(array $headers): array
    {
        $redacted = $headers;

        if (isset($redacted['Authorization'])) {
            $redacted['Authorization'] = 'Bearer [REDACTED]';
        }

        return $redacted;
    }
}
