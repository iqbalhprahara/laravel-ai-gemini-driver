<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Parsing;

use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Ursamajeur\CloudCodePA\Auth\ProjectResolver;
use Ursamajeur\CloudCodePA\Parsing\Concerns\ResolvesProject;

final class ChatRequestBuilder
{
    use ResolvesProject;

    public function __construct(
        private readonly string $project = '',
        private readonly ?ProjectResolver $projectResolver = null,
    ) {}

    /**
     * Build a v1internal envelope for the generateChat endpoint.
     *
     * @param  array<int, mixed>  $messages  laravel/ai message objects
     * @return array<string, mixed>
     */
    public function build(
        string $model,
        array $messages,
        ?string $systemInstruction = null,
    ): array {
        $userMessage = $this->extractLastUserMessage($messages);

        if ($systemInstruction !== null && $systemInstruction !== '') {
            $userMessage = $systemInstruction."\n\n".$userMessage;
        }

        return [
            'project' => $this->resolveProject(),
            'model_config_id' => $model,
            'user_message' => $userMessage,
            'metadata' => [
                'ideType' => '10',
            ],
        ];
    }

    /**
     * Extract the text content from the last user message.
     *
     * @param  array<int, mixed>  $messages
     */
    private function extractLastUserMessage(array $messages): string
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $message = $messages[$i];

            if ($message instanceof UserMessage) {
                return $message->content ?? '';
            }

            if ($message instanceof Message && $message->role->value === 'user') {
                return $message->content ?? '';
            }
        }

        return '';
    }
}
