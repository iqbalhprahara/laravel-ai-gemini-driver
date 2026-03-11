<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Contracts;

use Ursamajeur\CloudCodePA\Parsing\DTOs\GenerationResult;

interface ResponseMapperInterface
{
    /**
     * Map a JSON response body to a GenerationResult DTO.
     */
    public function mapFromBody(string $body): GenerationResult;

    /**
     * Extract the error message from an API error response body.
     */
    public function extractErrorMessage(string $body): string;
}
