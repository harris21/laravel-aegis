<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Console\Concerns;

use Illuminate\Console\Command;

/**
 * Safe string/array accessors over Symfony's broadly-typed option() and
 * argument(), whose declared return type widens across Laravel versions.
 *
 * @phpstan-require-extends Command
 */
trait ResolvesConsoleInput
{
    private function stringOption(string $name): string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : '';
    }

    private function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }

    /**
     * @return list<string>
     */
    private function stringListOption(string $name): array
    {
        $value = $this->option($name);

        return array_values(array_filter(is_array($value) ? $value : [], 'is_string'));
    }
}
