<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Console\Generators;

/**
 * Generates a test stub for a scaffolded Value Object.
 *
 * Pest gets `it(...)->todo();` — one pending assertion per generated file.
 * PHPUnit gets a `markTestIncomplete()` method on a TestCase subclass.
 * The caller decides which by passing $usePest; the command auto-detects
 * by checking for `vendor/pestphp/pest` in the consuming project.
 */
final class TestGenerator
{
    public function __construct(
        private readonly string $name,
        private readonly string $namespace,
        private readonly bool $usePest = true,
    ) {}

    public function generate(): string
    {
        $stubName = $this->usePest ? 'value-object-test' : 'value-object-test-phpunit';

        return strtr(ValueObjectGenerator::loadStub($stubName), [
            '{{ namespace }}' => $this->namespace,
            '{{ class }}' => $this->name,
        ]);
    }
}
