<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Parsing;

use Ursamajeur\CloudCodePA\Parsing\DTOs\GenerationResult;
use Ursamajeur\CloudCodePA\Parsing\DTOs\StreamChunkResult;
use Ursamajeur\CloudCodePA\Parsing\DTOs\ToolCallData;
use Ursamajeur\CloudCodePA\Parsing\DTOs\UsageData;

final class ResponseMapper
{
    /**
     * Decode a v1internal JSON response body and map to a GenerationResult DTO.
     *
     * This is the canonical entry point — all v1internal JSON decoding happens here.
     */
    public function mapFromBody(string $body): GenerationResult
    {
        /** @var array<string, mixed> $responseJson */
        $responseJson = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        return $this->map($responseJson);
    }

    /**
     * Extract the error message from a v1internal error response body.
     *
     * Keeps v1internal error envelope knowledge inside ResponseMapper.
     */
    public function extractErrorMessage(string $body): string
    {
        /** @var mixed $decoded */
        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            return (string) ($decoded['error']['message'] ?? $decoded['error'] ?? $body);
        }

        return $body !== '' ? $body : 'Unknown error';
    }

    /**
     * Map a single streaming chunk JSON to a StreamChunkResult DTO.
     *
     * Detects final chunk by presence of finishReason on the candidate.
     *
     * @param  array<string, mixed>  $chunkJson
     */
    public function mapChunk(array $chunkJson): StreamChunkResult
    {
        $text = $this->extractText($chunkJson);
        $toolCalls = $this->extractToolCalls($chunkJson);
        $finishReason = $chunkJson['candidates'][0]['finishReason'] ?? null;
        $isFinal = $finishReason !== null;

        $usage = null;
        $mappedFinishReason = null;

        if ($isFinal) {
            $usage = $this->extractUsage($chunkJson);
            $mappedFinishReason = $this->extractFinishReason($chunkJson);
        }

        return new StreamChunkResult(
            text: $text,
            isFinal: $isFinal,
            usage: $usage,
            finishReason: $mappedFinishReason,
            toolCalls: $toolCalls,
        );
    }

    /**
     * Map a v1internal JSON response array to a GenerationResult DTO.
     *
     * @param  array<string, mixed>  $responseJson
     */
    public function map(array $responseJson): GenerationResult
    {
        $text = $this->extractText($responseJson);
        $usage = $this->extractUsage($responseJson);
        $finishReason = $this->extractFinishReason($responseJson);
        $toolCalls = $this->extractToolCalls($responseJson);

        return new GenerationResult(
            text: $text,
            usage: $usage,
            finishReason: $finishReason,
            toolCalls: $toolCalls,
        );
    }

    /**
     * @param  array<string, mixed>  $responseJson
     */
    private function extractText(array $responseJson): string
    {
        $parts = $responseJson['candidates'][0]['content']['parts'] ?? [];
        $textParts = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $textParts[] = $part['text'];
            }
        }

        return implode('', $textParts);
    }

    /**
     * @param  array<string, mixed>  $responseJson
     */
    private function extractUsage(array $responseJson): UsageData
    {
        $metadata = $responseJson['usageMetadata'] ?? [];

        return new UsageData(
            promptTokenCount: (int) ($metadata['promptTokenCount'] ?? 0),
            candidatesTokenCount: (int) ($metadata['candidatesTokenCount'] ?? 0),
            totalTokenCount: (int) ($metadata['totalTokenCount'] ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>  $responseJson
     */
    private function extractFinishReason(array $responseJson): string
    {
        $reason = $responseJson['candidates'][0]['finishReason'] ?? 'UNKNOWN';

        return match ($reason) {
            'STOP' => 'stop',
            'MAX_TOKENS' => 'length',
            'SAFETY', 'RECITATION' => 'content_filter',
            default => 'unknown',
        };
    }

    /**
     * @param  array<string, mixed>  $responseJson
     * @return array<int, ToolCallData>
     */
    private function extractToolCalls(array $responseJson): array
    {
        $parts = $responseJson['candidates'][0]['content']['parts'] ?? [];
        $toolCalls = [];

        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                $toolCalls[] = new ToolCallData(
                    name: $part['functionCall']['name'],
                    arguments: $part['functionCall']['args'] ?? [],
                );
            }
        }

        return $toolCalls;
    }
}
