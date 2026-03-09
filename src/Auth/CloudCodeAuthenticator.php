<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Auth;

use Illuminate\Support\Facades\Log;
use Saloon\Contracts\Authenticator;
use Saloon\Http\PendingRequest;
use Ursamajeur\CloudCodePA\Contracts\CredentialStoreInterface;
use Ursamajeur\CloudCodePA\Exceptions\AuthenticationException;

final class CloudCodeAuthenticator implements Authenticator
{
    public function __construct(
        private readonly CredentialStoreInterface $credentialStore,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly bool $debug = false,
    ) {}

    public function set(PendingRequest $pendingRequest): void
    {
        if ($this->credentialStore->isExpired()) {
            $this->refreshToken();
        }

        $pendingRequest->headers()->add(
            'Authorization',
            "Bearer {$this->credentialStore->getAccessToken()}"
        );
    }

    public function refreshToken(): void
    {
        $refreshToken = $this->credentialStore->getRefreshToken();

        try {
            $connector = new OAuthConnector;
            $request = new TokenRefreshRequest(
                clientId: $this->clientId,
                clientSecret: $this->clientSecret,
                refreshToken: $refreshToken,
            );

            $response = $connector->send($request);

            if ($response->failed()) {
                throw AuthenticationException::refreshFailed(
                    $response->json('error_description', 'Unknown error')
                );
            }

            /** @var array{access_token: string, expires_in: int, token_type: string, refresh_token?: string} $data */
            $data = $response->json();

            $newRefreshToken = $data['refresh_token'] ?? $refreshToken;
            $expiresAt = time() + (int) $data['expires_in'];

            $this->credentialStore->updateCredentials(
                $data['access_token'],
                $newRefreshToken,
                $expiresAt,
            );

            if ($this->debug) {
                Log::debug('CloudCode-PA: Token refreshed', [
                    'type' => $data['token_type'],
                    'expires' => $expiresAt,
                ]);
            }
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw AuthenticationException::refreshFailed($e->getMessage());
        }
    }
}
