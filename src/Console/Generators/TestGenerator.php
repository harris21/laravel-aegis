<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Console\Generators;

/**
 * Generates the Pest test stub for a scaffolded Value Object.
 *
 * Per design: one `it(...)->todo();` per generated file. The user fills
 * in their own assertions; Aegis only names the file and points at the
 * Value Object it should exercise.
 */
final class TestGenerator
{
    public function __construct(
        private readonly string $name,
        private readonly string $namespace,
    ) {}

    public function generate(): string
    {
        $fqcn = $this->namespace.'\\'.$this->name;

        return <<<PHP
<?php

declare(strict_types=1);

use {$fqcn};

it('{$this->name}')->todo();

PHP;
    }
}
