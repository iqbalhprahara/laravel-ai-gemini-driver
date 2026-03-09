<?php

declare(strict_types=1);

use Ursamajeur\CloudCodePA\Parsing\DTOs\GenerationResult;
use Ursamajeur\CloudCodePA\Parsing\DTOs\UsageData;
use Ursamajeur\CloudCodePA\Parsing\ResponseMapper;

beforeEach(function (): void {
    $this->mapper = new ResponseMapper;
});

/** @return array<string, mixed> */
function loadFixture(string $name): array
{
    $path = __DIR__.'/../../Fixtures/responses/'.$name;

    return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

it('extracts text from single-part response', function (): void {
    $json = loadFixture('generate-content-success.json');
    $result = $this->mapper->map($json);

    expect($result)->toBeInstanceOf(GenerationResult::class);
    expect($result->text)->toBe('Hello! How can I help you today?');
});

it('concatenates multiple text parts', function (): void {
    $json = loadFixture('generate-content-multi-part.json');
    $result = $this->mapper->map($json);

    expect($result->text)->toBe('First part. Second part. Third part.');
});

it('extracts token usage metadata correctly', function (): void {
    $json = loadFixture('generate-content-success.json');
    $result = $this->mapper->map($json);

    expect($result->usage)->toBeInstanceOf(UsageData::class);
    expect($result->usage->promptTokenCount)->toBe(10);
    expect($result->usage->candidatesTokenCount)->toBe(25);
    expect($result->usage->totalTokenCount)->toBe(35);
});

it('maps STOP finish reason to stop', function (): void {
    $json = loadFixture('generate-content-success.json');
    $result = $this->mapper->map($json);

    expect($result->finishReason)->toBe('stop');
});

it('maps MAX_TOKENS finish reason to length', function (): void {
    $json = loadFixture('generate-content-max-tokens.json');
    $result = $this->mapper->map($json);

    expect($result->finishReason)->toBe('length');
});

it('maps SAFETY finish reason to content_filter', function (): void {
    $json = loadFixture('generate-content-safety.json');
    $result = $this->mapper->map($json);

    expect($result->finishReason)->toBe('content_filter');
});

it('handles missing usage metadata gracefully', function (): void {
    $json = [
        'candidates' => [
            [
                'content' => [
                    'parts' => [['text' => 'Response text']],
                    'role' => 'model',
                ],
                'finishReason' => 'STOP',
            ],
        ],
    ];

    $result = $this->mapper->map($json);

    expect($result->usage->promptTokenCount)->toBe(0);
    expect($result->usage->candidatesTokenCount)->toBe(0);
    expect($result->usage->totalTokenCount)->toBe(0);
});

it('returns readonly DTO properties', function (): void {
    $json = loadFixture('generate-content-success.json');
    $result = $this->mapper->map($json);

    $ref = new ReflectionClass($result);
    expect($ref->isReadOnly())->toBeTrue();

    $usageRef = new ReflectionClass($result->usage);
    expect($usageRef->isReadOnly())->toBeTrue();
});

it('extracts function call from response parts', function (): void {
    $json = [
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        [
                            'functionCall' => [
                                'name' => 'get_weather',
                                'args' => ['city' => 'London'],
                            ],
                        ],
                    ],
                    'role' => 'model',
                ],
                'finishReason' => 'STOP',
            ],
        ],
        'usageMetadata' => [
            'promptTokenCount' => 20,
            'candidatesTokenCount' => 10,
            'totalTokenCount' => 30,
        ],
    ];

    $result = $this->mapper->map($json);

    expect($result->toolCalls)->toHaveCount(1);
    expect($result->toolCalls[0]->name)->toBe('get_weather');
    expect($result->toolCalls[0]->arguments)->toBe(['city' => 'London']);
    expect($result->text)->toBe('');
});

it('maps from raw JSON body string via mapFromBody', function (): void {
    $body = (string) file_get_contents(__DIR__.'/../../Fixtures/responses/generate-content-success.json');
    $result = $this->mapper->mapFromBody($body);

    expect($result)->toBeInstanceOf(GenerationResult::class);
    expect($result->text)->toBe('Hello! How can I help you today?');
    expect($result->usage->promptTokenCount)->toBe(10);
});

it('extracts error message from v1internal error JSON', function (): void {
    $body = '{"error":{"code":429,"message":"Resource has been exhausted","status":"RESOURCE_EXHAUSTED"}}';

    expect($this->mapper->extractErrorMessage($body))->toBe('Resource has been exhausted');
});

it('returns raw body when error JSON has no message field', function (): void {
    $body = 'Service Unavailable';

    expect($this->mapper->extractErrorMessage($body))->toBe('Service Unavailable');
});

it('returns Unknown error for empty body', function (): void {
    expect($this->mapper->extractErrorMessage(''))->toBe('Unknown error');
});

it('handles mixed text and function call parts', function (): void {
    $json = [
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        ['text' => 'Let me check the weather for you.'],
                        [
                            'functionCall' => [
                                'name' => 'get_weather',
                                'args' => ['city' => 'Paris'],
                            ],
                        ],
                    ],
                    'role' => 'model',
                ],
                'finishReason' => 'STOP',
            ],
        ],
        'usageMetadata' => [
            'promptTokenCount' => 15,
            'candidatesTokenCount' => 20,
            'totalTokenCount' => 35,
        ],
    ];

    $result = $this->mapper->map($json);

    expect($result->text)->toBe('Let me check the weather for you.');
    expect($result->toolCalls)->toHaveCount(1);
    expect($result->toolCalls[0]->name)->toBe('get_weather');
});
