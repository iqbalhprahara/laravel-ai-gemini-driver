<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Parsing;

use Ursamajeur\CloudCodePA\Contracts\ResponseMapperInterface;
use Ursamajeur\CloudCodePA\Parsing\Concerns\ExtractsErrorMessages;
use Ursamajeur\CloudCodePA\Parsing\DTOs\GenerationResult;
use Ursamajeur\CloudCodePA\Parsing\DTOs\UsageData;

final class ChatResponseMapper implements ResponseMapperInterface
{
    use ExtractsErrorMessages;

    /**
     * Map a generateChat JSON response body to a GenerationResult DTO.
     *
     * The generateChat response shape: {"markdown":"...","processingDetails":{...},"fileUsage":{}}
     */
    public function mapFromBody(string $body): GenerationResult
    {
        /** @var array<string, mixed> $json */
        $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        return new GenerationResult(
            text: (string) ($json['markdown'] ?? ''),
            usage: new UsageData(
                promptTokenCount: 0,
                candidatesTokenCount: 0,
                totalTokenCount: 0,
            ),
            finishReason: 'stop',
        );
    }
}
