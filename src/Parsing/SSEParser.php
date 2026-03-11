<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Parsing;

use Generator;
use Illuminate\Support\Facades\Log;
use JsonException;
use Ursamajeur\CloudCodePA\Contracts\SSEParserInterface;

/**
 * Parses Server-Sent Events (SSE) byte streams into structured JSON chunks.
 *
 * Standalone class — no dependencies on Connector, Provider, or HTTP layer.
 * Each parsed chunk is yielded via Generator for immediate forwarding (zero buffering).
 * Malformed chunks are logged and skipped — the generator never crashes.
 */
final class SSEParser implements SSEParserInterface
{
    /**
     * Parse an SSE stream into individual JSON response arrays.
     *
     * Accepts a PHP stream resource (from Saloon/Guzzle response body).
     * Yields each `data: {JSON}` chunk as a decoded associative array.
     *
     * @param  resource  $stream
     * @return Generator<int, array<string, mixed>>
     */
    public function parse($stream): Generator
    {
        $buffer = '';

        try {
            foreach ($this->readLines($stream) as $line) {
                // SSE comments start with ':'
                if (str_starts_with($line, ':')) {
                    continue;
                }

                // Accumulate data lines (per SSE spec, consecutive data lines join with \n)
                if (str_starts_with($line, 'data:')) {
                    $payload = str_starts_with($line, 'data: ')
                        ? substr($line, 6)
                        : substr($line, 5);

                    if ($buffer !== '') {
                        $buffer .= "\n";
                    }
                    $buffer .= $payload;

                    continue;
                }

                // Empty line = end of SSE event — flush buffer
                if ($line === '' && $buffer !== '') {
                    // Handle [DONE] sentinel
                    if (trim($buffer) === '[DONE]') {
                        $buffer = '';

                        continue;
                    }

                    try {
                        /** @var array<string, mixed> $decoded */
                        $decoded = json_decode($buffer, true, 512, JSON_THROW_ON_ERROR);
                        yield $decoded;
                    } catch (JsonException $e) {
                        Log::warning('CloudCode-PA: Malformed SSE chunk skipped', [
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $buffer = '';
                }
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Read a stream resource line by line.
     *
     * @param  resource  $stream
     * @return Generator<int, string>
     */
    private function readLines($stream): Generator
    {
        while (! feof($stream)) {
            $line = fgets($stream);

            if ($line === false) {
                break;
            }

            yield rtrim($line, "\r\n");
        }
    }
}
