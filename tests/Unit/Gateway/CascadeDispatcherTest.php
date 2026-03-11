<?php

declare(strict_types=1);

use Saloon\Http\Response;
use Ursamajeur\CloudCodePA\Config\CascadeConfig;
use Ursamajeur\CloudCodePA\Exceptions\ApiException;
use Ursamajeur\CloudCodePA\Gateway\CascadeDispatcher;

function mockResponse(int $status): Response
{
    /** @var Response $response */
    $response = Mockery::mock(Response::class);
    $response->shouldReceive('status')->andReturn($status);

    return $response;
}

it('returns the first non-429 response', function (): void {
    $config = new CascadeConfig(
        enabled: true,
        steps: ['model-a', 'model-b'],
        triggerModel: 'model-a',
    );

    $dispatcher = new CascadeDispatcher(config: $config);

    $called = [];
    [$response, $model] = $dispatcher->dispatch('model-a', function (string $m) use (&$called): Response {
        $called[] = $m;

        return $m === 'model-a'
            ? mockResponse(ApiException::HTTP_RATE_LIMITED)
            : mockResponse(200);
    });

    expect($response->status())->toBe(200)
        ->and($model)->toBe('model-b')
        ->and($called)->toBe(['model-a', 'model-b']);
});

it('returns single model when cascade is not triggered', function (): void {
    $config = new CascadeConfig(
        enabled: true,
        steps: ['model-a', 'model-b'],
        triggerModel: 'model-a',
    );

    $dispatcher = new CascadeDispatcher(config: $config);

    [$response, $model] = $dispatcher->dispatch('model-b', function (string $m): Response {
        return mockResponse(200);
    });

    expect($response->status())->toBe(200)
        ->and($model)->toBe('model-b');
});

it('returns last 429 when all steps are exhausted', function (): void {
    $config = new CascadeConfig(
        enabled: true,
        steps: ['model-a', 'model-b'],
        triggerModel: 'model-a',
    );

    $dispatcher = new CascadeDispatcher(config: $config);

    [$response, $model] = $dispatcher->dispatch('model-a', function (string $m): Response {
        return mockResponse(ApiException::HTTP_RATE_LIMITED);
    });

    expect($response->status())->toBe(ApiException::HTTP_RATE_LIMITED)
        ->and($model)->toBe('model-b');
});

it('works without config (single-step, no cascade)', function (): void {
    $dispatcher = new CascadeDispatcher;

    [$response, $model] = $dispatcher->dispatch('any-model', function (string $m): Response {
        return mockResponse(200);
    });

    expect($response->status())->toBe(200)
        ->and($model)->toBe('any-model');
});

it('resolves cascade steps when model matches trigger', function (): void {
    $config = new CascadeConfig(
        enabled: true,
        steps: ['step-1', 'step-2', 'step-3'],
        triggerModel: 'step-1',
    );

    $dispatcher = new CascadeDispatcher(config: $config);

    expect($dispatcher->resolveSteps('step-1'))->toBe(['step-1', 'step-2', 'step-3']);
});

it('resolves single step when model does not match trigger', function (): void {
    $config = new CascadeConfig(
        enabled: true,
        steps: ['step-1', 'step-2'],
        triggerModel: 'step-1',
    );

    $dispatcher = new CascadeDispatcher(config: $config);

    expect($dispatcher->resolveSteps('other-model'))->toBe(['other-model']);
});
