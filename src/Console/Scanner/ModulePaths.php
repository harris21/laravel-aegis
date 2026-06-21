<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Console\Scanner;

/**
 * Discovers per-module {models, migrations} directory pairs for apps using
 * nwidart/laravel-modules, read entirely from that package's published config.
 *
 * Aegis takes no dependency on laravel-modules: when its config is absent (or
 * the configured modules directory does not exist) discovery returns an empty
 * list and vo:scan behaves exactly as it always has.
 */
final class ModulePaths
{
    /**
     * @return list<array{models: string, migrations: string}>
     */
    public static function discover(): array
    {
        $root = config('modules.paths.modules');

        if (! is_string($root) || ! is_dir($root)) {
            return [];
        }

        $modelSub = self::generatorPath('model', 'app/Models');
        $migrationSub = self::generatorPath('migration', 'database/migrations');

        $pairs = [];

        foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
            $pairs[] = [
                'models' => $moduleDir.'/'.$modelSub,
                'migrations' => $moduleDir.'/'.$migrationSub,
            ];
        }

        return $pairs;
    }

    private static function generatorPath(string $key, string $fallback): string
    {
        $value = config('modules.paths.generator.'.$key);

        if (is_array($value)) {
            $path = $value['path'] ?? null;

            return is_string($path) && $path !== '' ? $path : $fallback;
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $fallback;
    }
}
