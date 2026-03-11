<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Parsing\Concerns;

/**
 * Shared project ID resolution logic for request builders.
 *
 * Expects the using class to declare:
 *   - private readonly string $project
 *   - private readonly ?ProjectResolver $projectResolver
 */
trait ResolvesProject
{
    private function resolveProject(): string
    {
        if ($this->project !== '') {
            return $this->project;
        }

        if ($this->projectResolver !== null) {
            return $this->projectResolver->resolve();
        }

        return '';
    }
}
