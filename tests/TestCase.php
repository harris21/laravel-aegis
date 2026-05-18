<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Tests;

use HarrisRafto\Aegis\AegisServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [AegisServiceProvider::class];
    }
}
