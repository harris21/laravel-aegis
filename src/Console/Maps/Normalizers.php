<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Console\Maps;

use InvalidArgumentException;

/**
 * Maps a `--normalize=` flag entry to the inline PHP statement that
 * reassigns `$value` to its normalized form.
 *
 * Normalizers compose left-to-right when separated by commas, e.g.
 * `--normalize=trim,lower` emits `trim` first, then `mb_strtolower`.
 */
final class Normalizers
{
    /**
     * @param  list<string>  $normalizers
     * @return list<string>  Lines like "$value = mb_strtolower($value);", in order.
     */
    public static function resolveAll(array $normalizers): array
    {
        return array_map(self::resolveOne(...), $normalizers);
    }

    public static function resolveOne(string $normalizer): string
    {
        return match ($normalizer) {
            'lower' => '$value = mb_strtolower($value);',
            'upper' => '$value = mb_strtoupper($value);',
            'trim' => '$value = trim($value);',
            default => throw new InvalidArgumentException(
                "Unknown normalizer: {$normalizer}. Supported: lower, upper, trim."
            ),
        };
    }
}
