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

        if (self::columnAlreadyCast($location['body'], $column)) {
            return [
                'source' => $modelSource,
                'modified' => false,
                'alreadyPresent' => true,
            ];
        }

        $indent = self::detectIndent($location['body']);
        $castLine = sprintf(
            "%s'%s' => \\%s::class,",
            $indent,
            $column,
            ltrim($valueObjectFqcn, '\\'),
        );

        $insertion = self::buildInsertion($location['body'], $castLine, $indent);

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

    private static function buildInsertion(string $existingBody, string $castLine, string $indent): string
    {
        $trimmed = trim($existingBody);

        if ($trimmed === '') {
            $closingIndent = self::reduceIndent($indent);

            return "\n{$castLine}\n{$closingIndent}";
        }

        if (str_contains($existingBody, "\n")) {
            $withoutTrailingWhitespace = rtrim($existingBody);
            $trailing = substr($existingBody, strlen($withoutTrailingWhitespace));
            $withoutTrailingWhitespace = self::ensureTrailingComma($withoutTrailingWhitespace);

            return "{$withoutTrailingWhitespace}\n{$castLine}{$trailing}";
        }

        $existingEntries = self::ensureTrailingComma(trim($existingBody));
        $closingIndent = self::reduceIndent($indent);

        return "\n{$indent}{$existingEntries}\n{$castLine}\n{$closingIndent}";
    }

    private static function detectIndent(string $body): string
    {
        if (preg_match('/\n([ \t]+)\S/', $body, $matches) === 1) {
            return $matches[1];
        }

        // Fall back to four spaces of indent past the method body's own indent.
        // Laravel skeletons land at 12 spaces (3 × 4). Use that as the default.
        return '            ';
    }

    private static function reduceIndent(string $indent): string
    {
        // The closing `]` of the array sits one indentation level shallower
        // than its entries. Strip the rightmost four spaces (or one tab).
        if (str_ends_with($indent, '    ')) {
            return substr($indent, 0, -4);
        }

        if (str_ends_with($indent, "\t")) {
            return substr($indent, 0, -1);
        }

        return $indent;
    }

    private static function ensureTrailingComma(string $entries): string
    {
        return str_ends_with(rtrim($entries), ',')
            ? $entries
            : $entries.',';
    }
}
