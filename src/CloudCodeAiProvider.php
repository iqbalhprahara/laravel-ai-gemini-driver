<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA;

use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Providers\Concerns\GeneratesText;
use Laravel\Ai\Providers\Concerns\HasTextGateway;
use Laravel\Ai\Providers\Concerns\StreamsText;
use Laravel\Ai\Providers\Provider;

/**
 * Laravel AI SDK provider stub for the CloudCode-PA v1internal API.
 *
 * Registers 'cloudcode-pa' with Ai::extend(). Delegates to
 * CloudCodePrismProvider via PrismGateway (two-layer canonical pattern).
 * Full text/stream wiring is implemented in Epic 3.
 */
final class CloudCodeAiProvider extends Provider implements TextProvider
{
    use GeneratesText;
    use HasTextGateway;
    use StreamsText;

    /**
     * Get the name of the default text model.
     */
    public function defaultTextModel(): string
    {
        return (string) ($this->config['default_model'] ?? 'gemini-2.0-flash');
    }

    /**
     * Get the name of the cheapest text model.
     */
    public function cheapestTextModel(): string
    {
        return (string) ($this->config['cheapest_model'] ?? 'gemini-2.0-flash-lite');
    }

    /**
     * Get the name of the smartest text model.
     */
    public function smartestTextModel(): string
    {
        return (string) ($this->config['smartest_model'] ?? 'gemini-3.1-pro-high');
    }
}
