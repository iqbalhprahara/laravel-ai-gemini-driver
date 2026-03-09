<?php

declare(strict_types=1);

namespace Ursamajeur\CloudCodePA\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Ursamajeur\CloudCodePA\CloudCodeServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /** @return array<int, class-string<\Illuminate\Support\ServiceProvider>> */
    protected function getPackageProviders($app): array
    {
        return [
            CloudCodeServiceProvider::class,
        ];
    }
}
