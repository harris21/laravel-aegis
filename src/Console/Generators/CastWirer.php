<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Console\Generators;

use RuntimeException;

/**
 * Inserts a single cast entry into a Laravel model's `casts()` method.
 *
 * Scope (v0.1):
 *   - Targets the modern `protected function casts(): array { return [ ... ]; }` shape.
 *   - Idempotent: returns the source unchanged if the column is already cast.
 *   - Throws on shapes we can't safely modify (no casts() method, parent::casts() merges,
 *     non-array returns). The caller is expected to catch and fall back to printing the
 *     snippet for the user to paste manually.
 */
final class CastWirer
{
    /**
     * @return array{source: string, modified: bool, alreadyPresent: bool}
     */
    public static function wire(string $modelSource, string $column, string $valueObjectFqcn): array
    {
        $location = self::locateCastsArray($modelSource);

        $castLine = sprintf(
            "        '%s' => \\%s::class,",
            $column,
            ltrim($valueObjectFqcn, '\\'),
        );

        if (self::columnAlreadyCast($location['body'], $column)) {
            return [
                'source' => $modelSource,
                'modified' => false,
                'alreadyPresent' => true,
            ];
        }

        $insertion = self::buildInsertion($location['body'], $castLine);

        $modified = substr_replace(
            $modelSource,
            $insertion,
            $location['start'],
            $location['length'],
        );

        return [
            'source' => $modified,
            'modified' => true,
            'alreadyPresent' => false,
        ];
    }

    /**
     * @return array{start: int, length: int, body: string}
     */
    private static function locateCastsArray(string $source): array
    {
        // Match: `protected function casts(): array` then the first `return [ ... ];`
        // that follows it, capturing the body between the brackets.
        $pattern = '/protected\s+function\s+casts\s*\(\s*\)\s*:\s*array\s*\{[^}]*?return\s*\[(?P<body>[^]]*)\]\s*;/s';

        if (preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            throw new RuntimeException(
                "Could not locate a `casts(): array` method that returns an array literal. "
                ."Aegis only wires casts into models with the standard `return [ ... ];` shape."
            );
        }

        $body = $matches['body'][0];
        $bodyStart = $matches['body'][1];
        $bodyLength = strlen($body);

        return [
            'start' => $bodyStart,
            'length' => $bodyLength,
            'body' => $body,
        ];
    }

    private static function columnAlreadyCast(string $body, string $column): bool
    {
        // Look for `'column' =>` or `"column" =>`, allowing arbitrary whitespace.
        $pattern = sprintf('/[\'"]%s[\'"]\s*=>/u', preg_quote($column, '/'));

        return preg_match($pattern, $body) === 1;
    }

    private static function buildInsertion(string $existingBody, string $castLine): string
    {
        // Three layout cases for the existing array:
        //   1. Empty:           `return [];`            -> body is ""
        //   2. Single-line:     `return ['a' => 'b'];`  -> body has no leading newline
        //   3. Multi-line:      `return [\n    ...\n];` -> body starts and ends with \n
        //
        // We normalize all three to a multi-line form because that's how every
        // modern Laravel skeleton ships casts() and it leaves the cleanest diff.

        $trimmed = trim($existingBody);

        if ($trimmed === '') {
            return "\n{$castLine}\n    ";
        }

        // If the body already looks multi-line (newlines present), append our line
        // before whatever trailing whitespace closes it.
        if (str_contains($existingBody, "\n")) {
            $withoutTrailingWhitespace = rtrim($existingBody);
            $trailing = substr($existingBody, strlen($withoutTrailingWhitespace));

            // Ensure existing last entry has a trailing comma so adding our line is safe.
            $withoutTrailingWhitespace = self::ensureTrailingComma($withoutTrailingWhitespace);

            return "{$withoutTrailingWhitespace}\n{$castLine}{$trailing}";
        }

        // Single-line array — convert to multi-line so the diff stays clean.
        $existingEntries = self::ensureTrailingComma(trim($existingBody));

        return "\n        {$existingEntries}\n{$castLine}\n    ";
    }

    private static function ensureTrailingComma(string $entries): string
    {
        return str_ends_with(rtrim($entries), ',')
            ? $entries
            : $entries.',';
    }
}
