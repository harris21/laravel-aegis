<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Console\Scanner;

/**
 * Parses a model file's source and returns the columns it declares, plus any
 * casts already pointing at a class (which we count as "already wrapped" for
 * coverage purposes).
 *
 * Regex-based intentionally — same approach as CastWirer. We're not trying to
 * understand arbitrary PHP, only three declarations: $table, $fillable/$casts
 * properties, and the modern casts(): array method.
 */
final class ColumnExtractor
{
    /**
     * @return array{
     *   class: ?string,
     *   table: ?string,
     *   columns: list<string>,
     *   wrappedCasts: array<string,string>,
     * }
     */
    public static function fromSource(string $source): array
    {
        $class = self::extractClassName($source);
        $table = self::extractTable($source);

        $columns = [];
        $wrappedCasts = [];

        foreach (self::extractFillable($source) as $name) {
            $columns[] = $name;
        }

        foreach (self::extractCasts($source) as $name => $cast) {
            $columns[] = $name;

            if (self::isClassReference($cast)) {
                $wrappedCasts[$name] = self::normaliseCastClass($cast);
            }
        }

        return [
            'class' => $class,
            'table' => $table,
            'columns' => array_values(array_unique($columns)),
            'wrappedCasts' => $wrappedCasts,
        ];
    }

    private static function extractClassName(string $source): ?string
    {
        if (preg_match('/(?:final\s+|abstract\s+)?(?:readonly\s+)?class\s+(\w+)/i', $source, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private static function extractTable(string $source): ?string
    {
        if (preg_match('/protected\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]/', $source, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function extractFillable(string $source): array
    {
        if (preg_match('/protected\s+\$fillable\s*=\s*\[(.*?)\]\s*;/s', $source, $matches) !== 1) {
            return [];
        }

        return self::stringValuesFromArrayBody($matches[1]);
    }

    /**
     * @return array<string,string>  column name => cast value (raw token text)
     */
    private static function extractCasts(string $source): array
    {
        $bodies = [];

        if (preg_match('/protected\s+\$casts\s*=\s*\[(.*?)\]\s*;/s', $source, $m) === 1) {
            $bodies[] = $m[1];
        }

        if (preg_match('/protected\s+function\s+casts\s*\(\s*\)\s*:\s*array\s*\{[^}]*?return\s*\[(.*?)\]\s*;/s', $source, $m) === 1) {
            $bodies[] = $m[1];
        }

        $casts = [];

        foreach ($bodies as $body) {
            foreach (self::keyValuePairsFromArrayBody($body) as $key => $value) {
                $casts[$key] = $value;
            }
        }

        return $casts;
    }

    /**
     * @return list<string>
     */
    private static function stringValuesFromArrayBody(string $body): array
    {
        preg_match_all('/[\'"]([^\'"]+)[\'"]/', $body, $matches);

        return $matches[1];
    }

    /**
     * @return array<string,string>
     */
    private static function keyValuePairsFromArrayBody(string $body): array
    {
        $pairs = [];
        $pattern = '/[\'"]([^\'"]+)[\'"]\s*=>\s*([^,\n]+?)(?:,|$)/m';

        if (preg_match_all($pattern, $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $pairs[$match[1]] = trim($match[2]);
            }
        }

        return $pairs;
    }

    private static function isClassReference(string $cast): bool
    {
        $trimmed = trim($cast);

        return str_ends_with($trimmed, '::class')
            || str_contains($trimmed, '\\');
    }

    private static function normaliseCastClass(string $cast): string
    {
        $trimmed = trim($cast);
        $trimmed = preg_replace('/::class$/', '', $trimmed);

        return ltrim($trimmed ?? '', '\\');
    }
}
