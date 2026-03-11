<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Config;

final class ModelRouter
{
    /** @var list<string> */
    private readonly array $partnerPrefixes;

    /**
     * @param  list<string>  $partnerPrefixes  Model name prefixes routed to generateChat
     */
    public function __construct(
        array $partnerPrefixes = ['claude-', 'gpt-'],
    ) {
        $this->partnerPrefixes = $partnerPrefixes;
    }

    public function rpcFor(string $model): RpcType
    {
        foreach ($this->partnerPrefixes as $prefix) {
            if (str_starts_with($model, $prefix)) {
                return RpcType::GenerateChat;
            }
        }

        return RpcType::GenerateContent;
    }

    public function isPartnerModel(string $model): bool
    {
        return $this->rpcFor($model) === RpcType::GenerateChat;
    }
}
