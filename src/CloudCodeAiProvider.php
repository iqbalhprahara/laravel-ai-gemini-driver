<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA;

use Laravel\Ai\Contracts\Providers\RerankingProvider;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Providers\Concerns\GeneratesText;
use Laravel\Ai\Providers\Concerns\HasRerankingGateway;
use Laravel\Ai\Providers\Concerns\HasTextGateway;
use Laravel\Ai\Providers\Concerns\Reranks;
use Laravel\Ai\Providers\Concerns\StreamsText;
use Laravel\Ai\Providers\Provider;

/**
 * Laravel AI SDK provider for the CloudCode-PA v1internal API.
 *
 * Registered via Ai::extend('cloudcode-pa'). Delegates text generation
 * to CloudCodeGateway and reranking to CloudCodeRerankingGateway
 * (LLM-as-reranker via text generation pipeline).
 */
final class CloudCodeAiProvider extends Provider implements RerankingProvider, TextProvider
{
    use GeneratesText;
    use HasRerankingGateway;
    use HasTextGateway;
    use Reranks;
    use StreamsText;

    /**
     * Get the name of the default text model.
     */
    public function defaultTextModel(): string
    {
        return (string) $this->config['default_model'];
    }

    /**
     * Get the name of the cheapest text model.
     */
    public function cheapestTextModel(): string
    {
        return (string) $this->config['cheapest_model'];
    }

    /**
     * Get the name of the smartest text model.
     */
    public function smartestTextModel(): string
    {
        return (string) $this->config['smartest_model'];
    }

    /**
     * Get the name of the default reranking model.
     */
    public function defaultRerankingModel(): string
    {
        return (string) $this->config['default_reranking_model'];
    }
}
