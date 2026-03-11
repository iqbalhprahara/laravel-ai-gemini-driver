<?php

declare(strict_types=1);

use Saloon\Enums\Method;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\Requests\StreamContentRequest;

it('targets the streamGenerateContent endpoint', function (): void {
    $request = new StreamContentRequest(['model' => 'gemini-2.0-flash', 'request' => []]);

    expect($request->resolveEndpoint())->toBe(':streamGenerateContent');
});

it('uses POST method', function (): void {
    $request = new StreamContentRequest(['model' => 'gemini-2.0-flash', 'request' => []]);

    $reflection = new ReflectionProperty($request, 'method');
    expect($reflection->getValue($request))->toBe(Method::POST);
});

it('includes alt=sse query parameter', function (): void {
    $request = new StreamContentRequest(['model' => 'gemini-2.0-flash', 'request' => []]);

    $query = $request->query()->all();
    expect($query)->toHaveKey('alt');
    expect($query['alt'])->toBe('sse');
});

it('passes envelope as request body', function (): void {
    $envelope = [
        'model' => 'gemini-2.0-flash',
        'request' => [
            'contents' => [['role' => 'user', 'parts' => [['text' => 'Hello']]]],
        ],
    ];

    $request = new StreamContentRequest($envelope);
    $body = $request->body()->all();

    expect($body)->toBe($envelope);
});

it('sets stream timeout in config', function (): void {
    $request = new StreamContentRequest([], streamTimeout: 180);

    $config = $request->config()->all();
    expect($config['timeout'])->toBe(180);
    expect($config['stream'])->toBeTrue();
});

it('defaults stream timeout to 120 seconds', function (): void {
    $request = new StreamContentRequest([]);

    $config = $request->config()->all();
    expect($config['timeout'])->toBe(120);
});
