<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Gateway;

use Closure;
use Saloon\Http\Response;
use Ursamajeur\CloudCodePA\Config\CascadeConfig;
use Ursamajeur\CloudCodePA\Exceptions\ApiException;

/**
 * Handles model cascade fallback on 429 (rate limited) responses.
 *
 * Given a list of models to try (from CascadeConfig), dispatches requests
 * in order. Returns the first non-429 response, or the last 429 if all
 * steps are exhausted.
 */
final class CascadeDispatcher
{
    public function __construct(
        private readonly ?CascadeConfig $config = null,
    ) {}

    /**
     * Execute a request with cascade fallback on 429 responses.
     *
     * @param  Closure(string): Response  $sender  Callback that sends a request for the given model
     * @return array{Response, string} The response and the model that produced it
     */
    public function dispatch(string $model, Closure $sender): array
    {
        $steps = $this->resolveSteps($model);
        $lastResponse = null;

        foreach ($steps as $stepModel) {
            $response = $sender($stepModel);

            if ($response->status() !== ApiException::HTTP_RATE_LIMITED) {
                return [$response, $stepModel];
            }

            $lastResponse = $response;
        }

        /** @var Response $lastResponse */
        return [$lastResponse, $steps[count($steps) - 1]];
    }

    /**
     * Resolve the cascade steps for the requested model.
     *
     * Returns the full cascade step list if the model triggers cascade,
     * otherwise returns a single-element list with the requested model.
     *
     * @return list<string>
     */
    public function resolveSteps(string $model): array
    {
        if ($this->config !== null && $this->config->shouldCascade($model)) {
            return $this->config->steps;
        }

        return [$model];
    }
}
