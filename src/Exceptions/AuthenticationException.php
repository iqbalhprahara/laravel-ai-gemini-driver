<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Exceptions;

class AuthenticationException extends CloudCodeException
{
    public static function tokenExpired(): self
    {
        return new self(
            'Access token has expired. Run the PKCE authentication flow to obtain new credentials.'
        );
    }

    public static function credentialsNotFound(string $path): self
    {
        return new self(
            "Credential file not found at: {$path}. Run the PKCE authentication flow to create credentials."
        );
    }

    public static function refreshFailed(?string $reason = null): self
    {
        $message = 'Token refresh failed.';

        if ($reason !== null) {
            $message .= " Reason: {$reason}.";
        }

        return new self(
            "{$message} Run the PKCE authentication flow to re-authenticate."
        );
    }

    public static function invalidCredentials(): self
    {
        return new self(
            'Credential file is malformed or contains invalid data. Run the PKCE authentication flow to create new credentials.'
        );
    }
}
