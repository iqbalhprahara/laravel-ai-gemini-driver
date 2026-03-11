<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Parsing\Concerns;

/**
 * Shared error message extraction logic for response mappers.
 *
 * Both generateContent and generateChat error responses use the same
 * envelope structure: {"error": {"message": "..."}} or {"error": "..."}.
 */
trait ExtractsErrorMessages
{
    /**
     * Extract the error message from an API error response body.
     */
    public function extractErrorMessage(string $body): string
    {
        /** @var mixed $decoded */
        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            return (string) ($decoded['error']['message'] ?? $decoded['error'] ?? $body);
        }

        return $body !== '' ? $body : 'Unknown error';
    }
}
