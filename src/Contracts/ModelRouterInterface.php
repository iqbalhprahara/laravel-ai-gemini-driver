<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Contracts;

use Ursamajeur\CloudCodePA\Config\RpcType;

interface ModelRouterInterface
{
    /**
     * Determine the RPC type for the given model name.
     */
    public function rpcFor(string $model): RpcType;

    /**
     * Check if a model should be routed to a partner (non-Gemini) endpoint.
     */
    public function isPartnerModel(string $model): bool;
}
