<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Parsing\DTOs;

final readonly class UsageData
{
    public function __construct(
        public int $promptTokenCount,
        public int $candidatesTokenCount,
        public int $totalTokenCount,
    ) {}
}
