<?php

declare(strict_types=1);

use Ursamajeur\CloudCodePA\Contracts\ResponseMapperInterface;
use Ursamajeur\CloudCodePA\Parsing\ChatResponseMapper;
use Ursamajeur\CloudCodePA\Parsing\DTOs\GenerationResult;

it('implements ResponseMapperInterface', function (): void {
    $mapper = new ChatResponseMapper;

    expect($mapper)->toBeInstanceOf(ResponseMapperInterface::class);
});

it('maps a generateChat response body to GenerationResult', function (): void {
    $mapper = new ChatResponseMapper;
    $body = (string) json_encode(['markdown' => 'Hello from Claude!']);

    $result = $mapper->mapFromBody($body);

    expect($result)->toBeInstanceOf(GenerationResult::class)
        ->and($result->text)->toBe('Hello from Claude!')
        ->and($result->finishReason)->toBe('stop')
        ->and($result->usage->promptTokenCount)->toBe(0)
        ->and($result->usage->candidatesTokenCount)->toBe(0);
});

it('returns empty text when markdown field is missing', function (): void {
    $mapper = new ChatResponseMapper;
    $body = (string) json_encode(['otherField' => 'value']);

    $result = $mapper->mapFromBody($body);

    expect($result->text)->toBe('');
});

it('throws on invalid JSON body', function (): void {
    $mapper = new ChatResponseMapper;

    expect(fn () => $mapper->mapFromBody('not json'))
        ->toThrow(JsonException::class);
});

it('extracts error message from nested error structure', function (): void {
    $mapper = new ChatResponseMapper;
    $body = (string) json_encode(['error' => ['message' => 'Model not found']]);

    expect($mapper->extractErrorMessage($body))->toBe('Model not found');
});

it('extracts error message from flat error structure', function (): void {
    $mapper = new ChatResponseMapper;
    $body = (string) json_encode(['error' => 'Something went wrong']);

    expect($mapper->extractErrorMessage($body))->toBe('Something went wrong');
});

it('returns raw body when not valid JSON', function (): void {
    $mapper = new ChatResponseMapper;

    expect($mapper->extractErrorMessage('plain text error'))->toBe('plain text error');
});

it('returns Unknown error for empty body', function (): void {
    $mapper = new ChatResponseMapper;

    expect($mapper->extractErrorMessage(''))->toBe('Unknown error');
});
