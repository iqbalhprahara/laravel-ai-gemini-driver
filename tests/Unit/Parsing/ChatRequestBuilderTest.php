<?php

declare(strict_types=1);

use Laravel\Ai\Messages\UserMessage;
use Ursamajeur\CloudCodePA\Parsing\ChatRequestBuilder;

it('builds envelope with correct structure', function (): void {
    $builder = new ChatRequestBuilder(project: 'test-project');

    $envelope = $builder->build(
        model: 'claude-opus-4',
        messages: [new UserMessage('Hello Claude')],
    );

    expect($envelope)->toHaveKeys(['project', 'model_config_id', 'user_message', 'metadata'])
        ->and($envelope['project'])->toBe('test-project')
        ->and($envelope['model_config_id'])->toBe('claude-opus-4')
        ->and($envelope['user_message'])->toBe('Hello Claude')
        ->and($envelope['metadata']['ideType'])->toBe('10');
});

it('prepends system instruction to user message', function (): void {
    $builder = new ChatRequestBuilder(project: 'test-project');

    $envelope = $builder->build(
        model: 'claude-opus-4',
        messages: [new UserMessage('What is PHP?')],
        systemInstruction: 'You are a helpful assistant.',
    );

    expect($envelope['user_message'])
        ->toContain('You are a helpful assistant.')
        ->toContain('What is PHP?');
});

it('extracts the last user message from multiple messages', function (): void {
    $builder = new ChatRequestBuilder(project: 'test-project');

    $messages = [
        new UserMessage('First message'),
        new UserMessage('Second message'),
    ];

    $envelope = $builder->build(
        model: 'claude-opus-4',
        messages: $messages,
    );

    expect($envelope['user_message'])->toBe('Second message');
});

it('returns empty user_message when no user messages exist', function (): void {
    $builder = new ChatRequestBuilder(project: 'test-project');

    $envelope = $builder->build(
        model: 'claude-opus-4',
        messages: [],
    );

    expect($envelope['user_message'])->toBe('');
});

it('does not prepend empty system instruction', function (): void {
    $builder = new ChatRequestBuilder(project: 'test-project');

    $envelope = $builder->build(
        model: 'claude-opus-4',
        messages: [new UserMessage('Hello')],
        systemInstruction: '',
    );

    expect($envelope['user_message'])->toBe('Hello');
});
