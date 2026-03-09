<?php

declare(strict_types=1);

use Laravel\Ai\Files\Base64Document;
use Laravel\Ai\Files\Base64Image;
use Laravel\Ai\Messages\UserMessage;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Ursamajeur\CloudCodePA\Config\ModelRegistry;
use Ursamajeur\CloudCodePA\Parsing\RequestBuilder;
use Ursamajeur\CloudCodePA\Tests\Helpers\GatewayFactory;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\Requests\GenerateContentRequest;

it('produces correct inlineData format for base64 image', function (): void {
    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-success.json');

    $mockClient = new MockClient([
        GenerateContentRequest::class => MockResponse::make(body: $fixtureContent, status: 200),
    ]);

    $gateway = GatewayFactory::make($mockClient);

    // Simple fake image data
    $pngBase64 = base64_encode('fake-png-image-data');

    $image = new Base64Image($pngBase64, 'image/png');
    $message = new UserMessage('Describe this image.', [$image]);

    $gateway->generateText(
        provider: \Laravel\Ai\Ai::textProvider('cloudcode-pa'),
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: [$message],
    );

    $lastRequest = $mockClient->getLastPendingRequest();
    $body = $lastRequest->body()->all();
    $parts = $body['request']['contents'][0]['parts'];

    // First part: text
    expect($parts[0]['text'])->toBe('Describe this image.');

    // Second part: inlineData
    expect($parts[1])->toHaveKey('inlineData');
    expect($parts[1]['inlineData']['mimeType'])->toBe('image/png');
    expect($parts[1]['inlineData']['data'])->toBe($pngBase64);
});

it('produces correct inlineData format for base64 PDF document', function (): void {
    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-success.json');

    $mockClient = new MockClient([
        GenerateContentRequest::class => MockResponse::make(body: $fixtureContent, status: 200),
    ]);

    $gateway = GatewayFactory::make($mockClient);

    $pdfBase64 = base64_encode('%PDF-1.4 minimal test content');

    $document = new Base64Document($pdfBase64, 'application/pdf');
    $message = new UserMessage('Analyze this document.', [$document]);

    $gateway->generateText(
        provider: \Laravel\Ai\Ai::textProvider('cloudcode-pa'),
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: [$message],
    );

    $lastRequest = $mockClient->getLastPendingRequest();
    $body = $lastRequest->body()->all();
    $parts = $body['request']['contents'][0]['parts'];

    expect($parts[1]['inlineData']['mimeType'])->toBe('application/pdf');
    expect($parts[1]['inlineData']['data'])->toBe($pdfBase64);
});

it('handles multiple files in a single request', function (): void {
    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-success.json');

    $mockClient = new MockClient([
        GenerateContentRequest::class => MockResponse::make(body: $fixtureContent, status: 200),
    ]);

    $gateway = GatewayFactory::make($mockClient);

    $image1 = new Base64Image(base64_encode('fake-image-1'), 'image/jpeg');
    $image2 = new Base64Image(base64_encode('fake-image-2'), 'image/png');

    $message = new UserMessage('Compare these images.', [$image1, $image2]);

    $gateway->generateText(
        provider: \Laravel\Ai\Ai::textProvider('cloudcode-pa'),
        model: 'gemini-2.0-flash',
        instructions: null,
        messages: [$message],
    );

    $lastRequest = $mockClient->getLastPendingRequest();
    $body = $lastRequest->body()->all();
    $parts = $body['request']['contents'][0]['parts'];

    // Text + 2 images = 3 parts
    expect($parts)->toHaveCount(3);
    expect($parts[0]['text'])->toBe('Compare these images.');
    expect($parts[1]['inlineData']['mimeType'])->toBe('image/jpeg');
    expect($parts[2]['inlineData']['mimeType'])->toBe('image/png');
});

it('handles mixed text and file content with system instructions', function (): void {
    $fixtureContent = file_get_contents(__DIR__.'/../Fixtures/responses/generate-content-success.json');

    $mockClient = new MockClient([
        GenerateContentRequest::class => MockResponse::make(body: $fixtureContent, status: 200),
    ]);

    $gateway = GatewayFactory::make($mockClient);

    $document = new Base64Document(base64_encode('test content'), 'text/plain');
    $message = new UserMessage('Summarize this file.', [$document]);

    $gateway->generateText(
        provider: \Laravel\Ai\Ai::textProvider('cloudcode-pa'),
        model: 'gemini-2.0-flash',
        instructions: 'You are a document analyst.',
        messages: [$message],
    );

    $lastRequest = $mockClient->getLastPendingRequest();
    $body = $lastRequest->body()->all();

    // System instruction present
    expect($body['request']['systemInstruction']['parts'][0]['text'])->toBe('You are a document analyst.');

    // File content present
    $parts = $body['request']['contents'][0]['parts'];
    expect($parts[0]['text'])->toBe('Summarize this file.');
    expect($parts[1]['inlineData']['mimeType'])->toBe('text/plain');
});

it('preserves ordering of text and file parts', function (): void {
    $registry = new ModelRegistry(['gemini-2.0-flash' => 'gemini-2.0-flash']);
    $builder = new RequestBuilder(modelRegistry: $registry);

    $image = new Base64Image(base64_encode('image-data'), 'image/webp');
    $message = new UserMessage('What do you see?', [$image]);

    $envelope = $builder->build(
        model: 'gemini-2.0-flash',
        messages: [$message],
    );

    $parts = $envelope['request']['contents'][0]['parts'];

    // Text comes first, then inline data
    expect($parts[0])->toHaveKey('text');
    expect($parts[1])->toHaveKey('inlineData');
});
