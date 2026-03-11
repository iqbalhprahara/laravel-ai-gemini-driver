<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Contracts;

use Generator;

interface SSEParserInterface
{
    /**
     * Parse an SSE stream into individual JSON response arrays.
     *
     * @param  resource  $stream
     * @return Generator<int, array<string, mixed>>
     */
    public function parse($stream): Generator;
}
