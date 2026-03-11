<?php

declare(strict_types=1);

use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\ToolCall;
use Ursamajeur\CloudCodePA\Config\ModelRegistry;
use Ursamajeur\CloudCodePA\Parsing\RequestBuilder;

beforeEach(function (): void {
    $this->registry = new ModelRegistry([
        'gemini-2.0-flash' => 'gemini-2.0-flash',
        'gemini-2.5-pro' => 'gemini-2.5-pro',
    ]);

    $this->builder = new RequestBuilder(
        modelRegistry: $this->registry,
        project: 'test-project-id',
    );
});

it('produces correct v1internal JSON envelope structure', function (): void {
    $messages = [new UserMessage('Hello!')];

    $envelope = $this->builder->build(
        model: 'gemini-2.0-flash',
        messages: $messages,
    );

    expect($envelope)->toHaveKeys(['model', 'project', 'request']);
    expect($envelope['model'])->toBe('gemini-2.0-flash');
    expect($envelope['project'])->toBe('test-project-id');
    expect($envelope['request'])->toHaveKey('contents');
});

it('omits project when empty', function (): void {
    $builder = new RequestBuilder(
        modelRegistry: $this->registry,
        project: '',
    );

    $envelope = $builder->build(
        model: 'gemini-2.0-flash',
        messages: [new UserMessage('Hello!')],
    );

    expect($envelope)->toHaveKey('project')
        ->and($envelope['project'])->toBe('');
});

it('maps messages correctly to contents with role and parts', function (): void {
    $messages = [
        new UserMessage('What is PHP?'),
        new AssistantMessage('PHP is a server-side scripting language.'),
        new UserMessage('Tell me more.'),
    ];

    $envelope = $this->builder->build(
        model: 'gemini-2.0-flash',
        messages: $messages,
    );

    $contents = $envelope['request']['contents'];

    expect($contents)->toHaveCount(3);
    expect($contents[0]['role'])->toBe('user');
    expect($contents[0]['parts'][0]['text'])->toBe('What is PHP?');
    expect($contents[1]['role'])->toBe('model');
    expect($contents[1]['parts'][0]['text'])->toBe('PHP is a server-side scripting language.');
    expect($contents[2]['role'])->toBe('user');
    expect($contents[2]['parts'][0]['text'])->toBe('Tell me more.');
});

it('maps system instruction to systemInstruction format', function (): void {
    $envelope = $this->builder->build(
        model: 'gemini-2.0-flash',
        messages: [new UserMessage('Hello!')],
        systemInstruction: 'You are a helpful assistant.',
    );

    expect($envelope['request']['systemInstruction'])->toBe([
        'parts' => [['text' => 'You are a helpful assistant.']],
    ]);
});

it('omits systemInstruction when null', function (): void {
    $envelope = $this->builder->build(
        model: 'gemini-2.0-flash',
        messages: [new UserMessage('Hello!')],
        systemInstruction: null,
    );

    expect($envelope['request'])->not->toHaveKey('systemInstruction');
});

it('omits systemInstruction when empty string', function (): void {
    $envelope = $this->builder->build(
        model: 'gemini-2.0-flash',
        messages: [new UserMessage('Hello!')],
        systemInstruction: '',
    );

    expect($envelope['request'])->not->toHaveKey('systemInstruction');
});

it('maps generation config: temperature, maxOutputTokens, topP, topK', function (): void {
    $envelope = $this->builder->build(
        model: 'gemini-2.0-flash',
        messages: [new UserMessage('Hello!')],
        generationConfig: [
            'temperature' => 0.7,
            'maxOutputTokens' => 8192,
            'topP' => 0.95,
            'topK' => 40,
        ],
    );

    $config = $envelope['request']['generationConfig'];

    expect($config['temperature'])->toBe(0.7);
    expect($config['maxOutputTokens'])->toBe(8192);
    expect($config['topP'])->toBe(0.95);
    expect($config['topK'])->toBe(40);
});

it('maps max_tokens to maxOutputTokens', function (): void {
    $envelope = $this->builder->build(
        model: 'gemini-2.0-flash',
        messages: [new UserMessage('Hello!')],
        generationConfig: ['max_tokens' => 4096],
    );

    expect($envelope['request']['generationConfig']['maxOutputTokens'])->toBe(4096);
});

it('omits generationConfig when empty', function (): void {
    $envelope = $this->builder->build(
        model: 'gemini-2.0-flash',
        messages: [new UserMessage('Hello!')],
        generationConfig: [],
    );

    expect($envelope['request'])->not->toHaveKey('generationConfig');
});

it('resolves model via ModelRegistry (bare name)', function (): void {
    $envelope = $this->builder->build(
        model: 'gemini-2.5-pro',
        messages: [new UserMessage('Hello!')],
    );

    expect($envelope['model'])->toBe('gemini-2.5-pro');
});

it('throws on unknown model alias', function (): void {
    $this->builder->build(
        model: 'nonexistent-model',
        messages: [new UserMessage('Hello!')],
    );
})->throws(\Ursamajeur\CloudCodePA\Exceptions\CloudCodeException::class);

it('maps assistant messages with tool calls to model role with functionCall parts', function (): void {
    $toolCalls = collect([
        new ToolCall(id: 'call_1', name: 'get_weather', arguments: ['city' => 'London']),
    ]);

    $messages = [
        new UserMessage('What is the weather?'),
        new AssistantMessage('', $toolCalls),
    ];

    $envelope = $this->builder->build(
        model: 'gemini-2.0-flash',
        messages: $messages,
    );

    $contents = $envelope['request']['contents'];
    expect($contents[1]['role'])->toBe('model');
    expect($contents[1]['parts'][0]['functionCall'])->toBe([
        'name' => 'get_weather',
        'args' => ['city' => 'London'],
    ]);
});
