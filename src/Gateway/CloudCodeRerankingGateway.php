<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Gateway;

use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Gateway\Gateway;
use Laravel\Ai\Contracts\Gateway\RerankingGateway;
use Laravel\Ai\Contracts\Providers\RerankingProvider;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\RankedDocument;
use Laravel\Ai\Responses\RerankingResponse;
use Ursamajeur\CloudCodePA\Exceptions\CloudCodeException;

/**
 * LLM-as-reranker gateway that uses text generation to score document relevance.
 *
 * Constructs a structured prompt asking the LLM to score each document's
 * relevance to a query, then parses the JSON response into RankedDocument DTOs.
 * Reuses the full text generation pipeline (cascade, endpoint fallback, auth).
 */
final class CloudCodeRerankingGateway implements RerankingGateway
{
    public function __construct(
        private readonly Gateway $textGateway,
    ) {}

    /**
     * @param  array<int, string>  $documents
     */
    public function rerank(
        RerankingProvider $provider,
        string $model,
        array $documents,
        string $query,
        ?int $limit = null,
    ): RerankingResponse {
        if (! $provider instanceof TextProvider) {
            throw CloudCodeException::notImplemented('Reranking requires a provider that implements TextProvider');
        }

        /** @var string $providerName */
        $providerName = method_exists($provider, 'name') ? $provider->name() : 'cloudcode-pa';

        if ($documents === []) {
            return new RerankingResponse(
                [],
                new Meta($providerName, $model),
            );
        }

        $prompt = $this->buildScoringPrompt($documents, $query);

        $textResponse = $this->textGateway->generateText(
            provider: $provider,
            model: $model,
            instructions: $this->systemInstruction(),
            messages: [new \Laravel\Ai\Messages\UserMessage($prompt)],
        );

        $ranked = $this->parseScores($textResponse->text, $documents, $limit);

        return new RerankingResponse(
            $ranked,
            new Meta($providerName, $model),
        );
    }

    private function systemInstruction(): string
    {
        return <<<'INSTRUCTION'
You are a document relevance scorer. Given a query and numbered documents, you MUST respond with ONLY a JSON array scoring each document's relevance to the query.

Rules:
- Score each document from 0.0 (irrelevant) to 1.0 (perfectly relevant)
- Return ONLY valid JSON, no markdown fences, no explanation
- Format: [{"index": 0, "score": 0.95}, {"index": 1, "score": 0.32}]
- Every document MUST appear exactly once in the output
- Scores should reflect semantic relevance, not just keyword overlap
INSTRUCTION;
    }

    /**
     * @param  array<int, string>  $documents
     */
    private function buildScoringPrompt(array $documents, string $query): string
    {
        $docList = '';
        foreach ($documents as $index => $document) {
            $docList .= "[{$index}] {$document}\n";
        }

        return <<<PROMPT
Query: {$query}

Documents:
{$docList}
Score each document's relevance to the query. Respond with JSON only.
PROMPT;
    }

    /**
     * Parse LLM JSON response into sorted RankedDocument array.
     *
     * @param  array<int, string>  $documents
     * @return array<int, RankedDocument>
     *
     * @throws CloudCodeException
     */
    private function parseScores(string $text, array $documents, ?int $limit): array
    {
        $cleaned = trim($text);

        // Strip markdown code fences if present
        if (str_starts_with($cleaned, '```')) {
            $cleaned = (string) preg_replace('/^```(?:json)?\s*\n?/', '', $cleaned);
            $cleaned = (string) preg_replace('/\n?```\s*$/', '', $cleaned);
        }

        /** @var mixed $decoded */
        $decoded = json_decode($cleaned, true);

        if (! is_array($decoded)) {
            throw CloudCodeException::rerankingParseFailed($text);
        }

        $ranked = (new Collection($decoded))
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['index'], $item['score']))
            ->map(fn (array $item): RankedDocument => new RankedDocument(
                index: (int) $item['index'],
                document: $documents[(int) $item['index']] ?? '',
                score: (float) $item['score'],
            ))
            ->sortByDesc('score')
            ->values();

        if ($limit !== null) {
            $ranked = $ranked->take($limit);
        }

        return $ranked->all();
    }
}
