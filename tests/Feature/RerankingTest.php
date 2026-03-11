<?php

declare(strict_types=1);

use Laravel\Ai\Ai;
use Laravel\Ai\Responses\Data\RankedDocument;
use Laravel\Ai\Responses\RerankingResponse;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\Requests\GenerateContentRequest;

beforeEach(function (): void {
    config()->set(
        'cloudcode-pa.auth.credentials_path',
        __DIR__.'/../Fixtures/credentials/valid-credentials.json',
    );
    config()->set('cloudcode-pa.project', 'test-project-id');
});

afterEach(function (): void {
    MockClient::destroyGlobal();
});

it('reranks documents through the full provider stack', function (): void {
    // Arrange — mock the text generation response with ranking scores
    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/reranking-scores.json');

    MockClient::global([
        GenerateContentRequest::class => MockResponse::make(
            body: $fixtureContent,
            status: 200,
        ),
    ]);

    $provider = Ai::rerankingProvider('cloudcode-pa');

    $documents = [
        'PHP is a server-side scripting language.',
        'The weather in Tokyo is sunny today.',
        'Laravel is a PHP web framework.',
    ];

    // Act
    $response = $provider->rerank(
        documents: $documents,
        query: 'PHP frameworks',
    );

    // Assert
    expect($response)->toBeInstanceOf(RerankingResponse::class)
        ->and($response->results)->toHaveCount(3)
        ->and($response->first())->toBeInstanceOf(RankedDocument::class)
        ->and($response->first()->score)->toBe(0.95)
        ->and($response->first()->index)->toBe(0)
        ->and($response->meta->provider)->toBe('cloudcode-pa');
});

it('respects limit parameter', function (): void {
    // Arrange
    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/reranking-scores.json');

    MockClient::global([
        GenerateContentRequest::class => MockResponse::make(
            body: $fixtureContent,
            status: 200,
        ),
    ]);

    $provider = Ai::rerankingProvider('cloudcode-pa');

    // Act
    $response = $provider->rerank(
        documents: ['Doc A', 'Doc B', 'Doc C'],
        query: 'test',
        limit: 1,
    );

    // Assert — only top result
    expect($response->results)->toHaveCount(1)
        ->and($response->first()->score)->toBe(0.95);
});

it('uses default reranking model from config', function (): void {
    // Arrange
    config()->set('cloudcode-pa.default_reranking_model', 'gemini-2.5-flash');

    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/reranking-scores.json');

    MockClient::global([
        GenerateContentRequest::class => MockResponse::make(
            body: $fixtureContent,
            status: 200,
        ),
    ]);

    $provider = Ai::rerankingProvider('cloudcode-pa');

    // Assert — default model resolves without error
    expect($provider->defaultRerankingModel())->toBe('gemini-2.5-flash');
});

it('returns documents in ranked order via documents() helper', function (): void {
    // Arrange
    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/reranking-scores.json');

    MockClient::global([
        GenerateContentRequest::class => MockResponse::make(
            body: $fixtureContent,
            status: 200,
        ),
    ]);

    $provider = Ai::rerankingProvider('cloudcode-pa');
    $documents = ['First doc', 'Second doc', 'Third doc'];

    // Act
    $response = $provider->rerank(
        documents: $documents,
        query: 'test',
    );

    // Assert — documents() returns Collection in ranked order
    $ranked = $response->documents();
    expect($ranked)->toHaveCount(3)
        ->and($ranked->first())->toBeString();
});
