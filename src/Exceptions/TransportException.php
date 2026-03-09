<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Exceptions;

class TransportException extends CloudCodeException
{
    public static function connectionFailed(?\Throwable $previous = null): self
    {
        return new self(
            'Failed to establish connection to the API.',
            0,
            $previous,
        );
    }

    public static function timeout(?\Throwable $previous = null): self
    {
        return new self(
            'Request timeout while communicating with the API.',
            0,
            $previous,
        );
    }
}
