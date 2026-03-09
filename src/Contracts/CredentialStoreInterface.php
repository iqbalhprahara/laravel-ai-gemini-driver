<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Contracts;

interface CredentialStoreInterface
{
    public function getAccessToken(): string;

    public function getRefreshToken(): string;

    public function getTokenType(): string;

    public function isExpired(): bool;

    public function updateCredentials(string $accessToken, string $refreshToken, int $expiresAt): void;
}
