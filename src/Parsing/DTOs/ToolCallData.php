<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Parsing\DTOs;

final readonly class ToolCallData
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments,
    ) {}
}
