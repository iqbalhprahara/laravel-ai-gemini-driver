<?php

declare(strict_types=1);

use Ursamajeur\CloudCodePA\Config\ModelRouter;
use Ursamajeur\CloudCodePA\Config\RpcType;
use Ursamajeur\CloudCodePA\Contracts\ModelRouterInterface;

it('implements ModelRouterInterface', function (): void {
    $router = new ModelRouter;

    expect($router)->toBeInstanceOf(ModelRouterInterface::class);
});

it('routes claude models to GenerateChat', function (): void {
    $router = new ModelRouter;

    expect($router->rpcFor('claude-opus-4'))->toBe(RpcType::GenerateChat)
        ->and($router->rpcFor('claude-3.5-sonnet'))->toBe(RpcType::GenerateChat);
});

it('routes gpt models to GenerateChat', function (): void {
    $router = new ModelRouter;

    expect($router->rpcFor('gpt-4o'))->toBe(RpcType::GenerateChat)
        ->and($router->rpcFor('gpt-3.5-turbo'))->toBe(RpcType::GenerateChat);
});

it('routes gemini models to GenerateContent', function (): void {
    $router = new ModelRouter;

    expect($router->rpcFor('gemini-2.5-flash'))->toBe(RpcType::GenerateContent)
        ->and($router->rpcFor('gemini-2.5-pro'))->toBe(RpcType::GenerateContent)
        ->and($router->rpcFor('gemini-3-pro-preview'))->toBe(RpcType::GenerateContent);
});

it('identifies partner models correctly', function (): void {
    $router = new ModelRouter;

    expect($router->isPartnerModel('claude-opus-4'))->toBeTrue()
        ->and($router->isPartnerModel('gpt-4o'))->toBeTrue()
        ->and($router->isPartnerModel('gemini-2.5-flash'))->toBeFalse();
});

it('accepts custom partner prefixes', function (): void {
    $router = new ModelRouter(partnerPrefixes: ['custom-']);

    expect($router->rpcFor('custom-model'))->toBe(RpcType::GenerateChat)
        ->and($router->rpcFor('claude-opus-4'))->toBe(RpcType::GenerateContent);
});

it('falls back to GenerateContent for unknown models', function (): void {
    $router = new ModelRouter;

    expect($router->rpcFor('unknown-model'))->toBe(RpcType::GenerateContent);
});
