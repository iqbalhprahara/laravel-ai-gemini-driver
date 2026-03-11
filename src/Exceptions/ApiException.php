<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Exceptions;

class ApiException extends CloudCodeException
{
    public const HTTP_UNAUTHORIZED = 401;

    public const HTTP_RATE_LIMITED = 429;

    public const HTTP_SERVER_ERROR = 500;

    /**
     * @param  int  $statusCode  HTTP status code — also available via getCode()
     * @param  string  $errorMessage  Raw error description from the API response
     * @param  ?string  $model  Model alias that triggered the error (if known)
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $errorMessage,
        public readonly ?string $model = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public static function rateLimited(?string $model = null): self
    {
        $message = 'API rate limit exceeded.';

        if ($model !== null) {
            $message = "API rate limit exceeded for model: {$model}.";
        }

        return new self(
            message: $message,
            statusCode: self::HTTP_RATE_LIMITED,
            errorMessage: 'Rate Limited',
            model: $model,
        );
    }

    public static function serverError(int $statusCode, string $errorMessage, ?string $model = null): self
    {
        $message = "API server error ({$statusCode}): {$errorMessage}";

        if ($model !== null) {
            $message .= " [model: {$model}]";
        }

        return new self(
            message: $message,
            statusCode: $statusCode,
            errorMessage: $errorMessage,
            model: $model,
        );
    }

    public static function clientError(int $statusCode, string $errorMessage, ?string $model = null): self
    {
        $message = "API client error ({$statusCode}): {$errorMessage}";

        if ($model !== null) {
            $message .= " [model: {$model}]";
        }

        return new self(
            message: $message,
            statusCode: $statusCode,
            errorMessage: $errorMessage,
            model: $model,
        );
    }
}
