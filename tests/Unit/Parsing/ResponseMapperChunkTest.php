<?php

declare(strict_types=1);

use Ursamajeur\CloudCodePA\Parsing\DTOs\StreamChunkResult;
use Ursamajeur\CloudCodePA\Parsing\ResponseMapper;

beforeEach(function (): void {
    $this->mapper = new ResponseMapper;
});

it('extracts delta text from a streaming chunk', function (): void {
    $chunk = [
        'candidates' => [
            [
                'content' => [
                    'parts' => [['text' => 'Hello']],
                    'role' => 'model',
                ],
            ],
        ],
    ];

    $result = $this->mapper->mapChunk($chunk);

    expect($result)->toBeInstanceOf(StreamChunkResult::class);
    expect($result->text)->toBe('Hello');
    expect($result->isFinal)->toBeFalse();
    expect($result->usage)->toBeNull();
    expect($result->finishReason)->toBeNull();
});

it('detects final chunk with finish reason and usage', function (): void {
    $chunk = [
        'candidates' => [
            [
                'content' => [
                    'parts' => [['text' => '!']],
                    'role' => 'model',
                ],
                'finishReason' => 'STOP',
            ],
        ],
        'usageMetadata' => [
            'promptTokenCount' => 5,
            'candidatesTokenCount' => 3,
            'totalTokenCount' => 8,
        ],
    ];

    $result = $this->mapper->mapChunk($chunk);

    expect($result->isFinal)->toBeTrue();
    expect($result->finishReason)->toBe('stop');
    expect($result->usage)->not->toBeNull();
    expect($result->usage->promptTokenCount)->toBe(5);
    expect($result->usage->candidatesTokenCount)->toBe(3);
});

it('maps MAX_TOKENS finish reason on final chunk', function (): void {
    $chunk = [
        'candidates' => [
            [
                'content' => [
                    'parts' => [['text' => 'truncated']],
                    'role' => 'model',
                ],
                'finishReason' => 'MAX_TOKENS',
            ],
        ],
        'usageMetadata' => [
            'promptTokenCount' => 10,
            'candidatesTokenCount' => 100,
            'totalTokenCount' => 110,
        ],
    ];

    $result = $this->mapper->mapChunk($chunk);

    expect($result->finishReason)->toBe('length');
});

it('extracts tool calls from streaming chunk', function (): void {
    $chunk = [
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        [
                            'functionCall' => [
                                'name' => 'get_weather',
                                'args' => ['location' => 'London'],
                            ],
                        ],
                    ],
                    'role' => 'model',
                ],
            ],
        ],
    ];

    $result = $this->mapper->mapChunk($chunk);

    expect($result->toolCalls)->toHaveCount(1);
    expect($result->toolCalls[0]->name)->toBe('get_weather');
    expect($result->toolCalls[0]->arguments)->toBe(['location' => 'London']);
    expect($result->text)->toBe('');
});

it('handles chunk with empty parts', function (): void {
    $chunk = [
        'candidates' => [
            [
                'content' => [
                    'parts' => [],
                    'role' => 'model',
                ],
            ],
        ],
    ];

    $result = $this->mapper->mapChunk($chunk);

    expect($result->text)->toBe('');
    expect($result->isFinal)->toBeFalse();
});
