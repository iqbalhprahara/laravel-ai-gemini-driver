<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA;

use Generator;
use Illuminate\Http\Client\RequestException;
use Prism\Prism\Providers\Provider;
use Prism\Prism\Text\Request as TextRequest;
use Prism\Prism\Text\Response as TextResponse;

/**
 * Prism provider stub for the CloudCode-PA v1internal API.
 *
 * Registered via prism-manager->extend('cloudcode-pa'). The full
 * Saloon-based transport (text() + stream()) is wired in Epic 3.
 */
final class CloudCodePrismProvider extends Provider
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config, // @phpstan-ignore property.onlyWritten
    ) {}

    /**
     * @throws \BadMethodCallException
     */
    public function text(TextRequest $request): TextResponse
    {
        throw new \BadMethodCallException('Not yet implemented');
    }

    /**
     * @throws \BadMethodCallException
     */
    public function stream(TextRequest $request): Generator
    {
        throw new \BadMethodCallException('Not yet implemented');
    }

    /**
     * @throws \BadMethodCallException
     */
    public function handleRequestException(string $model, RequestException $e): never
    {
        throw new \BadMethodCallException('Not yet implemented');
    }
}
