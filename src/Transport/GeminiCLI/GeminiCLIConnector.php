<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Transport\GeminiCLI;

use Illuminate\Support\Facades\Log;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Ursamajeur\CloudCodePA\Auth\CloudCodeAuthenticator;

final class GeminiCLIConnector extends Connector
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly CloudCodeAuthenticator $cloudCodeAuth,
        private readonly int $timeout = 30,
        private readonly int $connectTimeout = 10,
        private readonly bool $debug = false,
    ) {}

    public function resolveBaseUrl(): string
    {
        return $this->baseUrl;
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
