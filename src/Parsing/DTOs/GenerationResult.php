<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Parsing\DTOs;

final readonly class GenerationResult
{
    /**
     * @param  array<int, ToolCallData>  $toolCalls
     */
    public function __construct(
        public string $text,
        public UsageData $usage,
        public string $finishReason,
        public array $toolCalls = [],
    ) {}
}
