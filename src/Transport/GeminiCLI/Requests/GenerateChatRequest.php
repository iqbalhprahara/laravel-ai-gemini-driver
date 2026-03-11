<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Transport\GeminiCLI\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class GenerateChatRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * @param  array<string, mixed>  $envelope  The generateChat envelope from ChatRequestBuilder
     */
    public function __construct(
        private readonly array $envelope,
    ) {}

    public function resolveEndpoint(): string
    {
        return ':generateChat';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->envelope;
    }
}
