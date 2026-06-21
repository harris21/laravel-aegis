<?php

declare(strict_types=1);

use HarrisRafto\Aegis\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function aegisRemoveDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    foreach (glob($dir.'/*') ?: [] as $path) {
        is_dir($path) ? aegisRemoveDir($path) : unlink($path);
    }

    rmdir($dir);
}
