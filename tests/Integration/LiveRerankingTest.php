<?php

declare(strict_types=1);

use Laravel\Ai\Ai;
use Laravel\Ai\Responses\Data\RankedDocument;
use Laravel\Ai\Responses\RerankingResponse;

/*
|--------------------------------------------------------------------------
| Live Integration Tests — Reranking via Real API
|--------------------------------------------------------------------------
|
| These tests hit the real CloudCode-PA API using LLM-as-reranker.
| They require:
|   1. Valid OAuth credentials at the configured credentials_path
|   2. CLOUDCODE_PA_CLIENT_ID and CLOUDCODE_PA_CLIENT_SECRET in .env
|
| Run with: ./vendor/bin/pest --group=integration
| Excluded from default test runs.
|
*/

beforeEach(function (): void {
    $projectRoot = dirname(__DIR__, 2);

    // Load .env from project root (Testbench doesn't load it automatically)
    \Dotenv\Dotenv::createImmutable($projectRoot)->safeLoad();

    // Override config with real values from .env
    config()->set('cloudcode-pa.auth.credentials_path', $projectRoot.'/storage/cloudcode-pa/oauth_creds.json');
    config()->set('cloudcode-pa.auth.client_id', env('CLOUDCODE_PA_CLIENT_ID', ''));
    config()->set('cloudcode-pa.auth.client_secret', env('CLOUDCODE_PA_CLIENT_SECRET', ''));
    config()->set('cloudcode-pa.debug', true);

    // Clear singletons so they re-resolve with updated config
    app()->forgetInstance(\Ursamajeur\CloudCodePA\Auth\CloudCodeAuthenticator::class);
    app()->forgetInstance(\Ursamajeur\CloudCodePA\Contracts\CredentialStoreInterface::class);
});

it('reranks documents using gemini-2.5-flash via real API', function (): void {
    $provider = Ai::rerankingProvider('cloudcode-pa');

    $documents = [
        'The Eiffel Tower is located in Paris, France.',
        'PHP was created by Rasmus Lerdorf in 1994.',
        'Laravel is a popular PHP framework for web development.',
        'The Great Wall of China is visible from space.',
        'Composer is the dependency manager for PHP.',
    ];

    $response = $provider->rerank(
        documents: $documents,
        query: 'PHP web development tools',
    );

    expect($response)->toBeInstanceOf(RerankingResponse::class)
        ->and($response->results)->toHaveCount(5)
        ->and($response->first())->toBeInstanceOf(RankedDocument::class)
        ->and($response->first()->score)->toBeGreaterThan(0.0)
        ->and($response->first()->score)->toBeLessThanOrEqual(1.0);

    // The PHP/Laravel/Composer documents should rank higher than geography docs
    $topIndices = array_map(fn (RankedDocument $r) => $r->index, array_slice($response->results, 0, 3));
    expect($topIndices)->toContain(1) // PHP
        ->toContain(2) // Laravel
        ->toContain(4); // Composer
})->group('integration');

it('reranks with limit via real API', function (): void {
    $provider = Ai::rerankingProvider('cloudcode-pa');

    $documents = [
        'Python is great for data science.',
        'Laravel uses Eloquent ORM for database access.',
        'React is a JavaScript library for building UIs.',
    ];

    $response = $provider->rerank(
        documents: $documents,
        query: 'PHP database',
        limit: 1,
    );

    expect($response->results)->toHaveCount(1)
        ->and($response->first()->score)->toBeGreaterThan(0.0)
        ->and($response->first()->index)->toBe(1); // Laravel/Eloquent
})->group('integration');

it('reranks using partner model claude-opus-4 via real API', function (): void {
    $provider = Ai::rerankingProvider('cloudcode-pa');

    $documents = [
        'Bananas are a tropical fruit.',
        'Kubernetes orchestrates container deployments.',
        'Docker containers package applications with dependencies.',
    ];

    $response = $provider->rerank(
        documents: $documents,
        query: 'container orchestration',
        model: 'claude-opus-4',
    );

    expect($response)->toBeInstanceOf(RerankingResponse::class)
        ->and($response->results)->toHaveCount(3)
        ->and($response->first()->score)->toBeGreaterThan(0.5);

    // Kubernetes and Docker should rank above bananas
    $topIndices = array_map(fn (RankedDocument $r) => $r->index, array_slice($response->results, 0, 2));
    expect($topIndices)->toContain(1) // Kubernetes
        ->toContain(2); // Docker
})->group('integration');
