<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Contracts;

interface RequestBuilderInterface
{
    /**
     * Build a v1internal envelope for the generateContent endpoint.
     *
     * @param  string  $model  Bare model name (already resolved via ModelRegistry)
     * @param  array<int, mixed>  $messages  laravel/ai message objects
     * @param  string|null  $systemInstruction  System instruction text
     * @param  array<string, mixed>  $generationConfig  Generation parameters (temperature, maxOutputTokens, etc.)
     * @param  array<int, mixed>  $tools  laravel/ai tool definitions
     * @return array<string, mixed> The v1internal envelope
     */
    public function build(
        string $model,
        array $messages,
        ?string $systemInstruction = null,
        array $generationConfig = [],
        array $tools = [],
    ): array;
}
