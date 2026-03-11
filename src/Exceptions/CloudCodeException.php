<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Exceptions;

use RuntimeException;

/**
 * Base exception for the CloudCode-PA driver.
 *
 * All package exceptions extend this class, enabling consumers
 * to catch driver-specific errors distinctly from unrelated exceptions.
 *
 * Hierarchy:
 *   CloudCodeException (this class)
 *   ├── AuthenticationException  — credential/token failures
 *   ├── ApiException             — 4xx/5xx from v1internal
 *   └── TransportException       — connection/timeout/network
 */
class CloudCodeException extends RuntimeException
{
    /**
     * Unknown model alias requested from the registry.
     *
     * @param  array<string>  $available
     */
    public static function unknownModel(string $alias, array $available): self
    {
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', mb_substr($alias, 0, 64));
        $list = implode(', ', $available);

        return new self(
            "Unknown model '{$sanitized}'. Available models: {$list}",
        );
    }

    /**
     * Feature not yet implemented in the current epic.
     */
    public static function notImplemented(string $method): self
    {
        return new self(
            "Method {$method} is not yet implemented.",
        );
    }

    /**
     * LLM reranking response could not be parsed as valid JSON scores.
     */
    public static function rerankingParseFailed(string $rawResponse): self
    {
        $preview = mb_substr($rawResponse, 0, 200);

        return new self(
            "Failed to parse reranking scores from LLM response. Preview: {$preview}",
        );
    }
}
