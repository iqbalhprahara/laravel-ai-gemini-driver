<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Config;

use Ursamajeur\CloudCodePA\Exceptions\CloudCodeException;

final class ModelRegistry
{
    /**
     * @param  array<string, string>  $models  Map of alias → bare model name
     */
    public function __construct(
        private readonly array $models,
    ) {}

    /**
     * Resolve a model alias to its bare v1internal model name.
     *
     * @throws CloudCodeException When alias is not in registry
     */
    public function resolve(string $alias): string
    {
        if (! isset($this->models[$alias])) {
            throw CloudCodeException::unknownModel(
                $alias,
                array_keys($this->models),
            );
        }

        return $this->models[$alias];
    }

    /**
     * Return all configured model aliases and their bare names.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->models;
    }

    /**
     * Check whether a model alias exists in the registry.
     */
    public function has(string $alias): bool
    {
        return isset($this->models[$alias]);
    }
}
