<?php

declare(strict_types=1);

use Laravel\Ai\Ai;
use Laravel\Ai\Files\Base64Document;
use Laravel\Ai\Files\Base64Image;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\TextEnd;
use Laravel\Ai\Streaming\Events\TextStart;

/*
|--------------------------------------------------------------------------
| Live Integration Tests — Real API Round-Trip
|--------------------------------------------------------------------------
|
| These tests hit the real CloudCode-PA API. They require:
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

it('generates text from the real CloudCode-PA API', function (): void {
    $provider = Ai::textProvider('cloudcode-pa');
    $gateway = $provider->textGateway();

    $response = $gateway->generateText(
        provider: $provider,
        model: 'gemini-2.5-flash',
        instructions: 'You are a helpful assistant. Respond in one short sentence.',
        messages: [new UserMessage('Say hello.')],
    );

    expect($response)->toBeInstanceOf(TextResponse::class)
        ->and($response->text)->toBeString()->not->toBeEmpty()
        ->and($response->usage)->toBeInstanceOf(Usage::class)
        ->and($response->usage->promptTokens)->toBeGreaterThan(0)
        ->and($response->usage->completionTokens)->toBeGreaterThan(0)
        ->and($response->meta)->toBeInstanceOf(Meta::class)
        ->and($response->meta->provider)->toBe('cloudcode-pa')
        ->and($response->meta->model)->toBe('gemini-2.5-flash');
})->group('integration');

it('streams text chunks from the real CloudCode-PA API', function (): void {
    $provider = Ai::textProvider('cloudcode-pa');
    $gateway = $provider->textGateway();

    $events = iterator_to_array($gateway->streamText(
        invocationId: 'integration-test',
        provider: $provider,
        model: 'gemini-2.5-flash',
        instructions: 'You are a helpful assistant. Respond in one short sentence.',
        messages: [new UserMessage('Count from 1 to 3.')],
    ));

    // Verify event sequence: StreamStart → TextStart → TextDelta(s) → TextEnd → StreamEnd
    expect($events[0])->toBeInstanceOf(StreamStart::class)
        ->and($events[0]->provider)->toBe('cloudcode-pa')
        ->and($events[0]->model)->toBe('gemini-2.5-flash');

    expect($events[1])->toBeInstanceOf(TextStart::class);

    $textDeltas = array_values(array_filter($events, fn ($e) => $e instanceof TextDelta));
    expect($textDeltas)->not->toBeEmpty();

    $combinedText = TextDelta::combine($events);
    expect($combinedText)->toBeString()->not->toBeEmpty();

    $lastTwo = array_slice($events, -2);
    expect($lastTwo[0])->toBeInstanceOf(TextEnd::class)
        ->and($lastTwo[1])->toBeInstanceOf(StreamEnd::class)
        ->and($lastTwo[1]->usage)->toBeInstanceOf(Usage::class)
        ->and($lastTwo[1]->usage->promptTokens)->toBeGreaterThan(0)
        ->and($lastTwo[1]->reason)->toBe('stop');
})->group('integration');

it('handles multi-turn conversation with the real API', function (): void {
    $provider = Ai::textProvider('cloudcode-pa');
    $gateway = $provider->textGateway();

    $response = $gateway->generateText(
        provider: $provider,
        model: 'gemini-2.5-flash',
        instructions: 'You are a helpful assistant. Respond in one short sentence.',
        messages: [
            new UserMessage('My name is TestUser.'),
            new \Laravel\Ai\Messages\AssistantMessage('Nice to meet you, TestUser!'),
            new UserMessage('What is my name?'),
        ],
    );

    expect($response)->toBeInstanceOf(TextResponse::class)
        ->and($response->text)->toBeString()->not->toBeEmpty()
        ->and(str_contains(strtolower($response->text), 'testuser'))->toBeTrue();
})->group('integration');

// ── Multimodal Input Tests ──────────────────────────────────────────

it('analyzes a base64 image via the real API', function (): void {
    // 1x1 red PNG pixel — minimal valid image encoded directly as base64
    $pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg==';

    $provider = Ai::textProvider('cloudcode-pa');
    $gateway = $provider->textGateway();

    $image = new Base64Image($pngBase64, 'image/png');
    $message = new UserMessage('What color is this image? Reply with just the color name.', [$image]);

    $response = $gateway->generateText(
        provider: $provider,
        model: 'gemini-2.5-flash',
        instructions: 'You are a helpful assistant. Respond in one word.',
        messages: [$message],
    );

    expect($response)->toBeInstanceOf(TextResponse::class)
        ->and($response->text)->toBeString()->not->toBeEmpty()
        ->and($response->usage->promptTokens)->toBeGreaterThan(0);
})->group('integration');

it('analyzes a base64 document via the real API', function (): void {
    // Minimal plain text document
    $textContent = "Project: CloudCode-PA Driver\nVersion: 1.0.0\nStatus: Integration Testing";
    $docBase64 = base64_encode($textContent);

    $provider = Ai::textProvider('cloudcode-pa');
    $gateway = $provider->textGateway();

    $document = new Base64Document($docBase64, 'text/plain');
    $message = new UserMessage('What is the project name mentioned in this document? Reply with just the name.', [$document]);

    $response = $gateway->generateText(
        provider: $provider,
        model: 'gemini-2.5-flash',
        instructions: 'You are a helpful assistant. Respond in one short phrase.',
        messages: [$message],
    );

    expect($response)->toBeInstanceOf(TextResponse::class)
        ->and($response->text)->toBeString()->not->toBeEmpty()
        ->and(str_contains(strtolower($response->text), 'cloudcode'))->toBeTrue();
})->group('integration');

it('streams response for multimodal input via the real API', function (): void {
    $textContent = "The quick brown fox jumps over the lazy dog.";
    $docBase64 = base64_encode($textContent);

    $provider = Ai::textProvider('cloudcode-pa');
    $gateway = $provider->textGateway();

    $document = new Base64Document($docBase64, 'text/plain');
    $message = new UserMessage('How many words are in this document? Reply with just the number.', [$document]);

    $events = iterator_to_array($gateway->streamText(
        invocationId: 'integration-multimodal-stream',
        provider: $provider,
        model: 'gemini-2.5-flash',
        instructions: 'You are a helpful assistant. Respond concisely.',
        messages: [$message],
    ));

    expect($events[0])->toBeInstanceOf(StreamStart::class);

    $textDeltas = array_values(array_filter($events, fn ($e) => $e instanceof TextDelta));
    expect($textDeltas)->not->toBeEmpty();

    $combinedText = TextDelta::combine($events);
    expect($combinedText)->toBeString()->not->toBeEmpty();

    $lastEvent = end($events);
    expect($lastEvent)->toBeInstanceOf(StreamEnd::class)
        ->and($lastEvent->reason)->toBe('stop');
})->group('integration');
