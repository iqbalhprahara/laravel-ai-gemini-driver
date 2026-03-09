<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Gateway;

use Closure;
use Generator;
use Laravel\Ai\Contracts\Files\TranscribableAudio;
use Laravel\Ai\Contracts\Gateway\Gateway;
use Laravel\Ai\Contracts\Providers\AudioProvider;
use Laravel\Ai\Contracts\Providers\EmbeddingProvider;
use Laravel\Ai\Contracts\Providers\ImageProvider;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Providers\TranscriptionProvider;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Responses\AudioResponse;
use Laravel\Ai\Responses\EmbeddingsResponse;
use Laravel\Ai\Responses\ImageResponse;
use Laravel\Ai\Responses\TextResponse;
use Laravel\Ai\Responses\TranscriptionResponse;
use Ursamajeur\CloudCodePA\Exceptions\CloudCodeException;

/**
 * Direct gateway for the CloudCode-PA v1internal API.
 *
 * Implements laravel/ai's Gateway contract directly.
 * Only text generation is supported — audio, embedding, image,
 * and transcription throw immediately.
 * Full Saloon-based transport is wired in Epic 3.
 */
final class CloudCodeGateway implements Gateway
{
    // ── Text (supported) ──────────────────────────────────────────

    /**
     * @param  array<int, mixed>  $messages
     * @param  array<int, mixed>  $tools
     *
     * @throws CloudCodeException
     */
    public function generateText(
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages = [],
        array $tools = [],
        ?array $schema = null,
        ?TextGenerationOptions $options = null,
        ?int $timeout = null,
    ): TextResponse {
        throw CloudCodeException::notImplemented('generateText()');
    }

    /**
     * @param  array<int, mixed>  $messages
     * @param  array<int, mixed>  $tools
     *
     * @throws CloudCodeException
     */
    public function streamText(
        string $invocationId,
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages = [],
        array $tools = [],
        ?array $schema = null,
        ?TextGenerationOptions $options = null,
        ?int $timeout = null,
    ): Generator {
        throw CloudCodeException::notImplemented('streamText()');
    }

    public function onToolInvocation(Closure $invoking, Closure $invoked): self
    {
        return $this;
    }

    // ── Unsupported capabilities ──────────────────────────────────

    /**
     * @throws CloudCodeException
     */
    public function generateAudio(
        AudioProvider $provider,
        string $model,
        string $text,
        string $voice,
        ?string $instructions = null,
    ): AudioResponse {
        throw CloudCodeException::notImplemented('generateAudio()');
    }

    /**
     * @param  array<int, string>  $inputs
     *
     * @throws CloudCodeException
     */
    public function generateEmbeddings(
        EmbeddingProvider $provider,
        string $model,
        array $inputs,
        int $dimensions,
    ): EmbeddingsResponse {
        throw CloudCodeException::notImplemented('generateEmbeddings()');
    }

    /**
     * @param  array<int, mixed>  $attachments
     *
     * @throws CloudCodeException
     */
    public function generateImage(
        ImageProvider $provider,
        string $model,
        string $prompt,
        array $attachments = [],
        ?string $size = null,
        ?string $quality = null,
        ?int $timeout = null,
    ): ImageResponse {
        throw CloudCodeException::notImplemented('generateImage()');
    }

    /**
     * @throws CloudCodeException
     */
    public function generateTranscription(
        TranscriptionProvider $provider,
        string $model,
        TranscribableAudio $audio,
        ?string $language = null,
        bool $diarize = false,
    ): TranscriptionResponse {
        throw CloudCodeException::notImplemented('generateTranscription()');
    }
}
