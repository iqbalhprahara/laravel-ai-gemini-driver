<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Exceptions;

class AuthenticationException extends CloudCodeException
{
    public static function tokenExpired(): self
    {
        return new self(
            'Access token has expired. Please provision a fresh credential file at the configured credentials_path.'
        );
    }

    public static function credentialsNotFound(string $path): self
    {
        return new self(
            "Credential file not found at: {$path}. Please provision an OAuth credential JSON file at this path."
        );
    }

    public static function refreshFailed(?string $reason = null): self
    {
        $message = 'Token refresh failed.';

        if ($reason !== null) {
            $message .= " Reason: {$reason}.";
        }

        return new self(
            "{$message} Please provision a fresh credential file at the configured credentials_path."
        );
    }

    public static function invalidCredentials(): self
    {
        return new self(
            'Credential file is malformed or contains invalid data. Please provision a valid OAuth credential JSON file.'
        );
    }
}
