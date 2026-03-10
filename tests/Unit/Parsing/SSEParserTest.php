<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Ursamajeur\CloudCodePA\Parsing\SSEParser;
use Ursamajeur\CloudCodePA\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->parser = new SSEParser;
});

/**
 * Helper: open a fixture file as a stream resource.
 *
 * @return resource
 */
function fixtureStream(string $filename)
{
    $path = __DIR__.'/../../Fixtures/streams/'.$filename;
    $stream = fopen($path, 'r');
    assert($stream !== false);

    return $stream;
}

/**
 * Helper: create an in-memory stream from a string.
 *
 * @return resource
 */
function stringStream(string $content)
{
    $stream = fopen('php://memory', 'r+');
    assert($stream !== false);
    fwrite($stream, $content);
    rewind($stream);

    return $stream;
}

it('parses multi-chunk SSE stream into correct number of JSON objects', function (): void {
    $chunks = iterator_to_array($this->parser->parse(fixtureStream('stream-success.txt')));

    expect($chunks)->toHaveCount(3);
    expect($chunks[0]['candidates'][0]['content']['parts'][0]['text'])->toBe('Hello');
    expect($chunks[1]['candidates'][0]['content']['parts'][0]['text'])->toBe(' world');
    expect($chunks[2]['candidates'][0]['content']['parts'][0]['text'])->toBe('!');
});

it('yields each chunk individually via generator', function (): void {
    $stream = fixtureStream('stream-success.txt');
    $generator = $this->parser->parse($stream);

    expect($generator)->toBeInstanceOf(Generator::class);

    // First yield
    $first = $generator->current();
    expect($first['candidates'][0]['content']['parts'][0]['text'])->toBe('Hello');

    // Advance to second
    $generator->next();
    $second = $generator->current();
    expect($second['candidates'][0]['content']['parts'][0]['text'])->toBe(' world');
});

it('skips malformed JSON chunks without crashing', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'Malformed SSE chunk');
        });

    $chunks = iterator_to_array($this->parser->parse(fixtureStream('stream-malformed-chunk.txt')));

    expect($chunks)->toHaveCount(2);
    expect($chunks[0]['candidates'][0]['content']['parts'][0]['text'])->toBe('Before');
    expect($chunks[1]['candidates'][0]['content']['parts'][0]['text'])->toBe('After');
});

it('ignores SSE comment lines', function (): void {
    $chunks = iterator_to_array($this->parser->parse(fixtureStream('stream-with-comments.txt')));

    expect($chunks)->toHaveCount(2);
    expect($chunks[0]['candidates'][0]['content']['parts'][0]['text'])->toBe('Hello');
    expect($chunks[1]['candidates'][0]['content']['parts'][0]['text'])->toBe(' there!');
});

it('handles empty data lines gracefully', function (): void {
    // An empty `data: ` line results in empty buffer — should be silently ignored
    $content = "data: \n\ndata: {\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"ok\"}],\"role\":\"model\"}}]}\n\n";

    $chunks = iterator_to_array($this->parser->parse(stringStream($content)));

    expect($chunks)->toHaveCount(1);
    expect($chunks[0]['candidates'][0]['content']['parts'][0]['text'])->toBe('ok');
});

it('handles data lines without space after colon', function (): void {
    $content = "data:{\"candidates\":[{\"content\":{\"parts\":[{\"text\":\"no space\"}],\"role\":\"model\"}}]}\n\n";

    $chunks = iterator_to_array($this->parser->parse(stringStream($content)));

    expect($chunks)->toHaveCount(1);
    expect($chunks[0]['candidates'][0]['content']['parts'][0]['text'])->toBe('no space');
});

it('handles DONE sentinel gracefully', function (): void {
    $chunks = iterator_to_array($this->parser->parse(fixtureStream('stream-with-done-sentinel.txt')));

    expect($chunks)->toHaveCount(1);
    expect($chunks[0]['candidates'][0]['content']['parts'][0]['text'])->toBe('Done test');
});

it('yields chunks with tool calls', function (): void {
    $chunks = iterator_to_array($this->parser->parse(fixtureStream('stream-with-tool-calls.txt')));

    expect($chunks)->toHaveCount(2);
    expect($chunks[0]['candidates'][0]['content']['parts'][0]['functionCall']['name'])->toBe('get_weather');
    expect($chunks[1]['candidates'][0]['content']['parts'][0]['text'])->toBe('The weather in London is sunny.');
});

it('extracts finish reason and usage from final chunk', function (): void {
    $chunks = iterator_to_array($this->parser->parse(fixtureStream('stream-success.txt')));

    $finalChunk = end($chunks);
    expect($finalChunk['candidates'][0]['finishReason'])->toBe('STOP');
    expect($finalChunk['usageMetadata']['promptTokenCount'])->toBe(5);
    expect($finalChunk['usageMetadata']['candidatesTokenCount'])->toBe(3);
});

it('closes stream resource after generator completes', function (): void {
    $stream = fixtureStream('stream-success.txt');

    // Consume the generator fully
    iterator_to_array($this->parser->parse($stream));

    expect(is_resource($stream))->toBeFalse();
});

it('closes stream resource when generator is abandoned early', function (): void {
    $stream = fixtureStream('stream-success.txt');
    $generator = $this->parser->parse($stream);

    // Get first chunk only
    $generator->current();

    // Abandon the generator (trigger __destruct via unset)
    unset($generator);

    expect(is_resource($stream))->toBeFalse();
});

it('handles completely empty stream', function (): void {
    $chunks = iterator_to_array($this->parser->parse(stringStream('')));

    expect($chunks)->toHaveCount(0);
});

it('concatenates consecutive data lines with newline separator per SSE spec', function (): void {
    $chunks = iterator_to_array($this->parser->parse(fixtureStream('stream-multiline-data.txt')));

    expect($chunks)->toHaveCount(2);
    expect($chunks[0]['candidates'][0]['content']['parts'][0]['text'])->toBe('Hello');
    expect($chunks[1]['candidates'][0]['content']['parts'][0]['text'])->toBe('!');
});

it('yields valid chunks mixed with malformed ones', function (): void {
    Log::shouldReceive('warning')->once();

    $chunks = iterator_to_array($this->parser->parse(fixtureStream('stream-malformed-chunk.txt')));

    expect($chunks)->toHaveCount(2);
    expect($chunks[0]['candidates'][0]['content']['parts'][0]['text'])->toBe('Before');
    expect($chunks[1]['candidates'][0]['content']['parts'][0]['text'])->toBe('After');
});
