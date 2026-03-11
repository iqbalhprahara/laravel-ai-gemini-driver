<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Auth;

use Illuminate\Support\Facades\Log;
use Ursamajeur\CloudCodePA\Exceptions\ApiException;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\GeminiCLIConnector;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\Requests\LoadCodeAssistRequest;

/**
 * Resolves the CloudCode-PA companion project ID by calling loadCodeAssist.
 *
 * The v1internal API requires a `project` field in every generateContent
 * request. This project ID is assigned during Gemini CLI onboarding and
 * returned by the loadCodeAssist RPC.
 */
final class ProjectResolver
{
    private ?string $cachedProjectId = null;

    public function __construct(
        private readonly GeminiCLIConnector $connector,
        private readonly bool $debug = false,
    ) {}

    /**
     * Get the project ID, calling loadCodeAssist if not already cached.
     *
     * @throws ApiException
     */
    public function resolve(): string
    {
        if ($this->cachedProjectId !== null && $this->cachedProjectId !== '') {
            return $this->cachedProjectId;
        }

        return $this->cachedProjectId = $this->callLoadCodeAssist();
    }

    /**
     * Pre-set the project ID (from config) to skip the loadCodeAssist call.
     */
    public function setProjectId(string $projectId): void
    {
        $this->cachedProjectId = $projectId;
    }

    private function callLoadCodeAssist(): string
    {
        $response = $this->connector->send(new LoadCodeAssistRequest);

        if ($response->failed()) {
            throw ApiException::serverError(
                $response->status(),
                'Failed to resolve project ID via loadCodeAssist: '.$response->body(),
                'loadCodeAssist',
            );
        }

        $projectId = $response->json('cloudaicompanionProject', '');

        if ($projectId === '' || $projectId === null) {
            throw ApiException::clientError(
                400,
                'loadCodeAssist did not return a cloudaicompanionProject. '
                .'Set CLOUDCODE_PA_PROJECT manually or ensure GOOGLE_CLOUD_PROJECT is set.',
                'loadCodeAssist',
            );
        }

        if ($this->debug) {
            Log::debug('CloudCode-PA: Resolved project ID', ['project' => $projectId]);
        }

        return (string) $projectId;
    }
}
