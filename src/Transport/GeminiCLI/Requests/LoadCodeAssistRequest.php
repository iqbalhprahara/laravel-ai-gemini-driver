<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Transport\GeminiCLI\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

final class LoadCodeAssistRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function resolveEndpoint(): string
    {
        return ':loadCodeAssist';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'metadata' => [
                'ideType' => 'IDE_UNSPECIFIED',
                'platform' => 'PLATFORM_UNSPECIFIED',
                'pluginType' => 'GEMINI',
            ],
        ];
    }
}
