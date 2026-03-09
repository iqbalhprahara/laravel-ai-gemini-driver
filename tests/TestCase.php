<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Tests;

use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Prism\Prism\PrismServiceProvider;
use Ursamajeur\CloudCodePA\CloudCodeServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /** @return array<int, class-string<\Illuminate\Support\ServiceProvider>> */
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            PrismServiceProvider::class,
            CloudCodeServiceProvider::class,
        ];
    }
}
