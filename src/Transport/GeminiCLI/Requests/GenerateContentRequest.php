<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Transport\GeminiCLI\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class GenerateContentRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  array<string, mixed>  $envelope  The v1internal envelope from RequestBuilder
     */
    public function __construct(
        private readonly array $envelope,
    ) {}

    public function resolveEndpoint(): string
    {
        return ':generateContent';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->envelope;
    }
}
