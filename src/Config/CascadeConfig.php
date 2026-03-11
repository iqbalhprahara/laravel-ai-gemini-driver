<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Config;

final readonly class CascadeConfig
{
    /**
     * @param  bool  $enabled  Whether cascade fallback is active
     * @param  list<string>  $steps  Ordered model names to try (best → worst)
     * @param  string  $triggerModel  The default model alias that activates the cascade
     */
    public function __construct(
        public bool $enabled,
        public array $steps,
        public string $triggerModel,
    ) {}

    public function shouldCascade(string $requestedModel): bool
    {
        return $this->enabled
            && $this->steps !== []
            && $requestedModel === $this->triggerModel;
    }
}
