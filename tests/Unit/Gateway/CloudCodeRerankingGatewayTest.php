<?php

declare(strict_types=1);

use Laravel\Ai\Contracts\Gateway\Gateway;
use Laravel\Ai\Contracts\Providers\RerankingProvider;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;
use Ursamajeur\CloudCodePA\Exceptions\CloudCodeException;
use Ursamajeur\CloudCodePA\Gateway\CloudCodeRerankingGateway;

beforeEach(function (): void {
    $this->mockTextGateway = Mockery::mock(Gateway::class);
    $this->rerankingGateway = new CloudCodeRerankingGateway(
        textGateway: $this->mockTextGateway,
    );

    // Provider must implement both RerankingProvider and TextProvider
    $this->mockProvider = Mockery::mock(RerankingProvider::class, TextProvider::class);
    $this->mockProvider->shouldReceive('name')->andReturn('cloudcode-pa');
});

it('returns empty response for empty documents', function (): void {
    // Act
    $response = $this->rerankingGateway->rerank(
        provider: $this->mockProvider,
        model: 'gemini-2.5-flash',
        documents: [],
        query: 'test query',
    );

    // Assert
    expect($response->results)->toBe([])
        ->and($response->meta->provider)->toBe('cloudcode-pa')
        ->and($response->meta->model)->toBe('gemini-2.5-flash');
});

it('parses valid JSON scores and ranks documents by score descending', function (): void {
    // Arrange
    $documents = [
        'PHP is a server-side scripting language.',
        'The weather in Tokyo is sunny today.',
        'Laravel is a PHP web framework.',
    ];

    $scoresJson = '[{"index": 0, "score": 0.72}, {"index": 1, "score": 0.15}, {"index": 2, "score": 0.95}]';

    $this->mockTextGateway->shouldReceive('generateText')
        ->once()
        ->withArgs(function (
            TextProvider $provider,
            string $model,
            ?string $instructions,
            array $messages,
        ): bool {
            // Verify the scoring prompt contains query and documents
            $message = $messages[0];
            expect($message)->toBeInstanceOf(UserMessage::class)
                ->and($message->content)->toContain('Query:')
                ->and($message->content)->toContain('[0]')
                ->and($message->content)->toContain('[1]')
                ->and($message->content)->toContain('[2]');

            return true;
        })
        ->andReturn(new TextResponse(
            text: $scoresJson,
            usage: new Usage(promptTokens: 100, completionTokens: 30),
            meta: new Meta('cloudcode-pa', 'gemini-2.5-flash'),
        ));

    // Act
    $response = $this->rerankingGateway->rerank(
        provider: $this->mockProvider,
        model: 'gemini-2.5-flash',
        documents: $documents,
        query: 'PHP frameworks',
    );

    // Assert — sorted by score descending
    expect($response->results)->toHaveCount(3)
        ->and($response->results[0]->index)->toBe(2)
        ->and($response->results[0]->score)->toBe(0.95)
        ->and($response->results[0]->document)->toBe('Laravel is a PHP web framework.')
        ->and($response->results[1]->index)->toBe(0)
        ->and($response->results[1]->score)->toBe(0.72)
        ->and($response->results[2]->index)->toBe(1)
        ->and($response->results[2]->score)->toBe(0.15);
});

it('applies limit to results', function (): void {
    // Arrange
    $documents = ['Doc A', 'Doc B', 'Doc C'];
    $scoresJson = '[{"index": 0, "score": 0.9}, {"index": 1, "score": 0.5}, {"index": 2, "score": 0.7}]';

    $this->mockTextGateway->shouldReceive('generateText')
        ->once()
        ->andReturn(new TextResponse(
            text: $scoresJson,
            usage: new Usage(promptTokens: 50, completionTokens: 20),
            meta: new Meta('cloudcode-pa', 'gemini-2.5-flash'),
        ));

    // Act
    $response = $this->rerankingGateway->rerank(
        provider: $this->mockProvider,
        model: 'gemini-2.5-flash',
        documents: $documents,
        query: 'test',
        limit: 2,
    );

    // Assert — only top 2
    expect($response->results)->toHaveCount(2)
        ->and($response->results[0]->score)->toBe(0.9)
        ->and($response->results[1]->score)->toBe(0.7);
});

it('handles markdown-fenced JSON response', function (): void {
    // Arrange
    $documents = ['Doc A', 'Doc B'];
    $fencedJson = "```json\n[{\"index\": 0, \"score\": 0.8}, {\"index\": 1, \"score\": 0.3}]\n```";

    $this->mockTextGateway->shouldReceive('generateText')
        ->once()
        ->andReturn(new TextResponse(
            text: $fencedJson,
            usage: new Usage(promptTokens: 50, completionTokens: 20),
            meta: new Meta('cloudcode-pa', 'gemini-2.5-flash'),
        ));

    // Act
    $response = $this->rerankingGateway->rerank(
        provider: $this->mockProvider,
        model: 'gemini-2.5-flash',
        documents: $documents,
        query: 'test',
    );

    // Assert
    expect($response->results)->toHaveCount(2)
        ->and($response->results[0]->score)->toBe(0.8);
});

it('throws CloudCodeException on unparseable response', function (): void {
    // Arrange
    $documents = ['Doc A'];

    $this->mockTextGateway->shouldReceive('generateText')
        ->once()
        ->andReturn(new TextResponse(
            text: 'I cannot rank these documents because...',
            usage: new Usage(promptTokens: 50, completionTokens: 30),
            meta: new Meta('cloudcode-pa', 'gemini-2.5-flash'),
        ));

    // Act & Assert
    expect(fn () => $this->rerankingGateway->rerank(
        provider: $this->mockProvider,
        model: 'gemini-2.5-flash',
        documents: $documents,
        query: 'test',
    ))->toThrow(CloudCodeException::class, 'Failed to parse reranking scores');
});

it('uses system instruction requesting JSON-only output', function (): void {
    // Arrange
    $documents = ['Doc A'];
    $capturedInstruction = null;

    $this->mockTextGateway->shouldReceive('generateText')
        ->once()
        ->withArgs(function (
            TextProvider $provider,
            string $model,
            ?string $instructions,
        ) use (&$capturedInstruction): bool {
            $capturedInstruction = $instructions;

            return true;
        })
        ->andReturn(new TextResponse(
            text: '[{"index": 0, "score": 0.5}]',
            usage: new Usage(promptTokens: 50, completionTokens: 10),
            meta: new Meta('cloudcode-pa', 'gemini-2.5-flash'),
        ));

    // Act
    $this->rerankingGateway->rerank(
        provider: $this->mockProvider,
        model: 'gemini-2.5-flash',
        documents: $documents,
        query: 'test',
    );

    // Assert
    expect($capturedInstruction)
        ->toContain('relevance scorer')
        ->toContain('JSON')
        ->toContain('0.0')
        ->toContain('1.0');
});

it('passes the requested model to text gateway', function (): void {
    // Arrange
    $documents = ['Doc A'];
    $capturedModel = null;

    $this->mockTextGateway->shouldReceive('generateText')
        ->once()
        ->withArgs(function (
            TextProvider $provider,
            string $model,
        ) use (&$capturedModel): bool {
            $capturedModel = $model;

            return true;
        })
        ->andReturn(new TextResponse(
            text: '[{"index": 0, "score": 0.5}]',
            usage: new Usage(promptTokens: 50, completionTokens: 10),
            meta: new Meta('cloudcode-pa', 'gemini-2.5-flash'),
        ));

    // Act
    $this->rerankingGateway->rerank(
        provider: $this->mockProvider,
        model: 'claude-opus-4',
        documents: $documents,
        query: 'test',
    );

    // Assert
    expect($capturedModel)->toBe('claude-opus-4');
});

it('skips malformed entries in JSON response', function (): void {
    // Arrange
    $documents = ['Doc A', 'Doc B'];
    $partialJson = '[{"index": 0, "score": 0.8}, {"bad": "entry"}, {"index": 1, "score": 0.3}]';

    $this->mockTextGateway->shouldReceive('generateText')
        ->once()
        ->andReturn(new TextResponse(
            text: $partialJson,
            usage: new Usage(promptTokens: 50, completionTokens: 20),
            meta: new Meta('cloudcode-pa', 'gemini-2.5-flash'),
        ));

    // Act
    $response = $this->rerankingGateway->rerank(
        provider: $this->mockProvider,
        model: 'gemini-2.5-flash',
        documents: $documents,
        query: 'test',
    );

    // Assert — malformed entry filtered out
    expect($response->results)->toHaveCount(2)
        ->and($response->results[0]->score)->toBe(0.8)
        ->and($response->results[1]->score)->toBe(0.3);
});
