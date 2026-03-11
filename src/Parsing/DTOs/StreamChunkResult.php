<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Parsing\DTOs;

final readonly class StreamChunkResult
{
    /**
     * @param  array<int, ToolCallData>  $toolCalls
     */
    public function __construct(
        public string $text,
        public bool $isFinal,
        public ?UsageData $usage = null,
        public ?string $finishReason = null,
        public array $toolCalls = [],
    ) {}
}
