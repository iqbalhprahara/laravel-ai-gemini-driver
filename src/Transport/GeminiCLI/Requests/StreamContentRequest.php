<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Transport\GeminiCLI\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class StreamContentRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  array<string, mixed>  $envelope  The v1internal envelope from RequestBuilder
     * @param  int  $streamTimeout  Stream timeout in seconds
     */
    public function __construct(
        private readonly array $envelope,
        private readonly int $streamTimeout = 120,
    ) {}

    public function resolveEndpoint(): string
    {
        return ':streamGenerateContent';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return [
            'alt' => 'sse',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->envelope;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => $this->streamTimeout,
            'stream' => true,
        ];
    }
}
