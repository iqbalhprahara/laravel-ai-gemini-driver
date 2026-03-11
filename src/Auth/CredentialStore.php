<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Auth;

use Ursamajeur\CloudCodePA\Contracts\CredentialStoreInterface;
use Ursamajeur\CloudCodePA\Exceptions\AuthenticationException;

final class CredentialStore implements CredentialStoreInterface
{
    /** @var array{access_token: string, refresh_token: string, token_type: string, expires_at: int}|null */
    private ?array $credentials = null;

    public function __construct(
        private readonly string $credentialsPath,
    ) {}

    public function getAccessToken(): string
    {
        return $this->loadCredentials()['access_token'];
    }

    public function getRefreshToken(): string
    {
        return $this->loadCredentials()['refresh_token'];
    }

    public function getTokenType(): string
    {
        return $this->loadCredentials()['token_type'];
    }

    public function isExpired(): bool
    {
        return $this->loadCredentials()['expires_at'] <= time();
    }

    public function updateCredentials(string $accessToken, string $refreshToken, int $expiresAt): void
    {
        $data = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt,
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        file_put_contents($this->credentialsPath, $json, LOCK_EX);
        chmod($this->credentialsPath, 0600);

        $this->credentials = $data;
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_at: int}
     */
    private function loadCredentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        if (! file_exists($this->credentialsPath)) {
            throw AuthenticationException::credentialsNotFound($this->credentialsPath);
        }

        $contents = file_get_contents($this->credentialsPath);

        if ($contents === false) {
            throw AuthenticationException::credentialsNotFound($this->credentialsPath);
        }

        /** @var mixed $decoded */
        $decoded = json_decode($contents, true);

        if (! is_array($decoded)
            || ! isset($decoded['access_token'], $decoded['refresh_token'], $decoded['token_type'], $decoded['expires_at'])
            || ! is_string($decoded['access_token'])
            || ! is_string($decoded['refresh_token'])
            || ! is_string($decoded['token_type'])
            || ! is_int($decoded['expires_at'])
        ) {
            throw AuthenticationException::invalidCredentials();
        }

        /** @var array{access_token: string, refresh_token: string, token_type: string, expires_at: int} $decoded */
        $this->credentials = [
            'access_token' => $decoded['access_token'],
            'refresh_token' => $decoded['refresh_token'],
            'token_type' => $decoded['token_type'],
            'expires_at' => $decoded['expires_at'],
        ];

        return $this->credentials;
    }
}
