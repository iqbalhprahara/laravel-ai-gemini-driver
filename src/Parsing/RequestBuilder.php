<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Parsing;

use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Ursamajeur\CloudCodePA\Config\ModelRegistry;
use Ursamajeur\CloudCodePA\Auth\ProjectResolver;
use Ursamajeur\CloudCodePA\Contracts\RequestBuilderInterface;

final class RequestBuilder implements RequestBuilderInterface
{
    public function __construct(
        private readonly ModelRegistry $modelRegistry,
        private readonly string $project = '',
        private readonly ?ProjectResolver $projectResolver = null,
    ) {}

    /**
     * @param  array<int, mixed>  $messages
     * @param  array<string, mixed>  $generationConfig
     * @param  array<int, mixed>  $tools
     * @return array<string, mixed>
     */
    public function build(
        string $model,
        array $messages,
        ?string $systemInstruction = null,
        array $generationConfig = [],
        array $tools = [],
    ): array {
        $resolvedModel = $this->modelRegistry->resolve($model);

        $request = [
            'contents' => $this->mapMessages($messages),
        ];

        if ($systemInstruction !== null && $systemInstruction !== '') {
            $request['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]],
            ];
        }

        $mappedConfig = $this->mapGenerationConfig($generationConfig);
        if ($mappedConfig !== []) {
            $request['generationConfig'] = $mappedConfig;
        }

        if ($tools !== []) {
            $request['tools'] = $this->mapTools($tools);
        }

        $envelope = [
            'model' => $resolvedModel,
            'project' => $this->resolveProject(),
            'request' => $request,
        ];

        return $envelope;
    }

    /**
     * Resolve the project ID from config or via loadCodeAssist.
     */
    private function resolveProject(): string
    {
        if ($this->project !== '') {
            return $this->project;
        }

        if ($this->projectResolver !== null) {
            return $this->projectResolver->resolve();
        }

        return '';
    }

    /**
     * @param  array<int, mixed>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function mapMessages(array $messages): array
    {
        $contents = [];

        foreach ($messages as $message) {
            if ($message instanceof ToolResultMessage) {
                $contents = array_merge($contents, $this->mapToolResultMessage($message));

                continue;
            }

            if ($message instanceof AssistantMessage) {
                $contents[] = $this->mapAssistantMessage($message);

                continue;
            }

            if ($message instanceof UserMessage) {
                $contents[] = $this->mapUserMessage($message);

                continue;
            }

            if ($message instanceof Message) {
                $contents[] = [
                    'role' => $this->mapRole($message),
                    'parts' => [['text' => $message->content ?? '']],
                ];
            }
        }

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapUserMessage(UserMessage $message): array
    {
        $parts = [];

        if ($message->content !== null && $message->content !== '') {
            $parts[] = ['text' => $message->content];
        }

        $parts = array_merge($parts, $this->mapAttachments($message));

        return [
            'role' => 'user',
            'parts' => $parts,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapAttachments(UserMessage $message): array
    {
        $parts = [];

        foreach ($message->attachments as $attachment) {
            $data = $attachment->toArray();

            if (isset($data['base64'], $data['mime'])) {
                $parts[] = [
                    'inlineData' => [
                        'mimeType' => $data['mime'],
                        'data' => $data['base64'],
                    ],
                ];
            }
        }

        return $parts;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAssistantMessage(AssistantMessage $message): array
    {
        $parts = [];

        if ($message->content !== null && $message->content !== '') {
            $parts[] = ['text' => $message->content];
        }

        foreach ($message->toolCalls as $toolCall) {
            $parts[] = [
                'functionCall' => [
                    'name' => $toolCall->name,
                    'args' => $toolCall->arguments,
                ],
            ];
        }

        if ($parts === []) {
            $parts[] = ['text' => ''];
        }

        return [
            'role' => 'model',
            'parts' => $parts,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapToolResultMessage(ToolResultMessage $message): array
    {
        $parts = [];

        foreach ($message->toolResults as $toolResult) {
            $result = $toolResult->result;
            $responseData = is_array($result) ? $result : ['result' => (string) $result];

            $parts[] = [
                'functionResponse' => [
                    'name' => $toolResult->name,
                    'response' => $responseData,
                ],
            ];
        }

        return [
            [
                'role' => 'user',
                'parts' => $parts,
            ],
        ];
    }

    private function mapRole(Message $message): string
    {
        return match ($message->role->value) {
            'assistant' => 'model',
            default => 'user',
        };
    }

    /**
     * @param  array<string, mixed>  $generationConfig
     * @return array<string, mixed>
     */
    private function mapGenerationConfig(array $generationConfig): array
    {
        $mapped = [];

        if (isset($generationConfig['temperature'])) {
            $mapped['temperature'] = (float) $generationConfig['temperature'];
        }

        if (isset($generationConfig['maxOutputTokens'])) {
            $mapped['maxOutputTokens'] = (int) $generationConfig['maxOutputTokens'];
        }

        if (isset($generationConfig['max_tokens'])) {
            $mapped['maxOutputTokens'] = (int) $generationConfig['max_tokens'];
        }

        if (isset($generationConfig['topP'])) {
            $mapped['topP'] = (float) $generationConfig['topP'];
        }

        if (isset($generationConfig['topK'])) {
            $mapped['topK'] = (int) $generationConfig['topK'];
        }

        return $mapped;
    }

    /**
     * @param  array<int, mixed>  $tools
     * @return array<int, array<string, mixed>>
     */
    private function mapTools(array $tools): array
    {
        $functionDeclarations = [];

        foreach ($tools as $tool) {
            if ($tool instanceof \Laravel\Ai\Contracts\Tool) {
                $schema = new \Illuminate\JsonSchema\JsonSchemaTypeFactory;
                $toolSchema = $tool->schema($schema);

                $functionDeclarations[] = [
                    'name' => $this->getToolName($tool),
                    'description' => (string) $tool->description(),
                    'parameters' => $toolSchema,
                ];
            }
        }

        if ($functionDeclarations === []) {
            return [];
        }

        return [
            ['functionDeclarations' => $functionDeclarations],
        ];
    }

    private function getToolName(\Laravel\Ai\Contracts\Tool $tool): string
    {
        $className = (new \ReflectionClass($tool))->getShortName();

        // Convert PascalCase to snake_case
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
    }
}
