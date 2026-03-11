<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Gateway;

use Closure;
use Generator;
use GuzzleHttp\Psr7\StreamWrapper;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Files\TranscribableAudio;
use Laravel\Ai\Contracts\Gateway\Gateway;
use Laravel\Ai\Contracts\Providers\AudioProvider;
use Laravel\Ai\Contracts\Providers\EmbeddingProvider;
use Laravel\Ai\Contracts\Providers\ImageProvider;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Providers\TranscriptionProvider;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Responses\AudioResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\EmbeddingsResponse;
use Laravel\Ai\Responses\ImageResponse;
use Laravel\Ai\Responses\TextResponse;
use Laravel\Ai\Responses\TranscriptionResponse;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\TextEnd;
use Laravel\Ai\Streaming\Events\TextStart;
use Laravel\Ai\Streaming\Events\ToolCall as ToolCallEvent;
use Saloon\Exceptions\Request\FatalRequestException;
use Ursamajeur\CloudCodePA\Exceptions\ApiException;
use Ursamajeur\CloudCodePA\Exceptions\AuthenticationException;
use Ursamajeur\CloudCodePA\Exceptions\CloudCodeException;
use Ursamajeur\CloudCodePA\Exceptions\TransportException;
use Ursamajeur\CloudCodePA\Parsing\DTOs\GenerationResult;
use Ursamajeur\CloudCodePA\Parsing\RequestBuilder;
use Ursamajeur\CloudCodePA\Parsing\ResponseMapper;
use Ursamajeur\CloudCodePA\Parsing\SSEParser;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\GeminiCLIConnector;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\Requests\GenerateContentRequest;
use Ursamajeur\CloudCodePA\Transport\GeminiCLI\Requests\StreamContentRequest;

/**
 * Direct gateway for the CloudCode-PA v1internal API.
 *
 * Implements laravel/ai's Gateway contract directly.
 * Only text generation is supported — audio, embedding, image,
 * and transcription throw immediately.
 */
final class CloudCodeGateway implements Gateway
{
    public function __construct(
        private readonly GeminiCLIConnector $connector,
        private readonly RequestBuilder $requestBuilder,
        private readonly ResponseMapper $responseMapper,
        private readonly SSEParser $sseParser = new SSEParser,
        private readonly int $streamTimeout = 120,
    ) {}

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
        $generationConfig = $this->buildGenerationConfig($options);

        $envelope = $this->requestBuilder->build(
            model: $model,
            messages: $messages,
            systemInstruction: $instructions,
            generationConfig: $generationConfig,
            tools: $tools,
        );

        $request = new GenerateContentRequest($envelope);

        try {
            $response = $this->connector->send($request);
        } catch (FatalRequestException $e) {
            $message = $e->getMessage();
            if (str_contains(strtolower($message), 'timeout') || str_contains(strtolower($message), 'timed out')) {
                throw TransportException::timeout($e);
            }
            throw TransportException::connectionFailed($e);
        }

        if ($response->failed()) {
            $this->handleErrorResponse($response->status(), $response->body(), $model);
        }

        $result = $this->responseMapper->mapFromBody($response->body());

        return $this->buildTextResponse($result, $model, $messages, $tools);
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
        $generationConfig = $this->buildGenerationConfig($options);

        $envelope = $this->requestBuilder->build(
            model: $model,
            messages: $messages,
            systemInstruction: $instructions,
            generationConfig: $generationConfig,
            tools: $tools,
        );

        $request = new StreamContentRequest($envelope, $this->streamTimeout);

        try {
            $response = $this->connector->send($request);
        } catch (FatalRequestException $e) {
            $message = $e->getMessage();
            if (str_contains(strtolower($message), 'timeout') || str_contains(strtolower($message), 'timed out')) {
                throw TransportException::timeout($e);
            }
            throw TransportException::connectionFailed($e);
        }

        if ($response->failed()) {
            $this->handleErrorResponse($response->status(), $response->body(), $model);
        }

        $stream = StreamWrapper::getResource($response->stream());
        $messageId = (string) Str::ulid();

        yield new StreamStart(
            id: (string) Str::ulid(),
            provider: 'cloudcode-pa',
            model: $model,
            timestamp: time(),
        );

        yield new TextStart(
            id: (string) Str::ulid(),
            messageId: $messageId,
            timestamp: time(),
        );

        $finalUsage = new Usage;
        $finalReason = 'stop';
        $toolCallIndex = 0;

        foreach ($this->sseParser->parse($stream) as $chunkJson) {
            $chunk = $this->responseMapper->mapChunk($chunkJson);

            if ($chunk->text !== '') {
                yield new TextDelta(
                    id: (string) Str::ulid(),
                    messageId: $messageId,
                    delta: $chunk->text,
                    timestamp: time(),
                );
            }

            foreach ($chunk->toolCalls as $toolCall) {
                yield new ToolCallEvent(
                    id: (string) Str::ulid(),
                    toolCall: new ToolCall(
                        id: "call_{$toolCallIndex}",
                        name: $toolCall->name,
                        arguments: $toolCall->arguments,
                    ),
                    timestamp: time(),
                );
                $toolCallIndex++;
            }

            if ($chunk->isFinal) {
                if ($chunk->usage !== null) {
                    $finalUsage = new Usage(
                        promptTokens: $chunk->usage->promptTokenCount,
                        completionTokens: $chunk->usage->candidatesTokenCount,
                    );
                }

                if ($chunk->finishReason !== null) {
                    $finalReason = $chunk->finishReason;
                }
            }
        }

        yield new TextEnd(
            id: (string) Str::ulid(),
            messageId: $messageId,
            timestamp: time(),
        );

        yield new StreamEnd(
            id: (string) Str::ulid(),
            reason: $finalReason,
            usage: $finalUsage,
            timestamp: time(),
        );
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

    // ── Error handling ─────────────────────────────────────────────

    /**
     * @throws ApiException
     * @throws AuthenticationException
     */
    private function handleErrorResponse(int $statusCode, string $body, string $model): never
    {
        $errorMessage = $this->responseMapper->extractErrorMessage($body);

        match (true) {
            $statusCode === 429 => throw ApiException::rateLimited($model),
            $statusCode === 401 => throw AuthenticationException::tokenExpired(),
            $statusCode >= 500 => throw ApiException::serverError($statusCode, $errorMessage, $model),
            default => throw ApiException::clientError($statusCode, $errorMessage, $model),
        };
    }

    // ── Private helpers ───────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function buildGenerationConfig(?TextGenerationOptions $options): array
    {
        if ($options === null) {
            return [];
        }

        $config = [];

        if ($options->temperature !== null) {
            $config['temperature'] = $options->temperature;
        }

        if ($options->maxTokens !== null) {
            $config['maxOutputTokens'] = $options->maxTokens;
        }

        return $config;
    }

    /**
     * @param  array<int, mixed>  $messages
     * @param  array<int, mixed>  $tools
     */
    private function buildTextResponse(
        GenerationResult $result,
        string $model,
        array $messages,
        array $tools,
    ): TextResponse {
        $usage = new Usage(
            promptTokens: $result->usage->promptTokenCount,
            completionTokens: $result->usage->candidatesTokenCount,
        );

        $meta = new Meta(
            provider: 'cloudcode-pa',
            model: $model,
        );

        $toolCalls = Collection::make($result->toolCalls)->map(
            fn ($tc, $index) => new ToolCall(
                id: "call_{$index}",
                name: $tc->name,
                arguments: $tc->arguments,
            ),
        );

        $response = new TextResponse(
            text: $result->text,
            usage: $usage,
            meta: $meta,
        );

        if ($toolCalls->isNotEmpty()) {
            $assistantMessage = new AssistantMessage($result->text, $toolCalls);
            $allMessages = Collection::make($messages);
            $allMessages->push($assistantMessage);

            $response->withMessages($allMessages);
        }

        return $response;
    }
}
