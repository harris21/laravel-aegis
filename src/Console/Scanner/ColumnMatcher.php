<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Console\Scanner;

/**
 * Maps a column name to a Value Object suggestion.
 *
 * The matcher is intentionally narrow: a small set of patterns that map cleanly
 * to single-property Value Objects. Patterns the matcher can't translate
 * confidently (status enums, money) return a candidate marker instead of a
 * runnable command so users still drive the decision.
 */
final class ColumnMatcher
{
    /**
     * @return array{vo: string, flags: array<string,string>}|array{candidate: true, note: string}|null
     */
    public static function match(string $column): array|null
    {
        $lower = strtolower($column);

        if ($lower === 'email' || str_ends_with($lower, '_email')) {
            return ['vo' => 'Email', 'flags' => ['rule' => 'email', 'normalize' => 'lower']];
        }

        if ($lower === 'url' || str_ends_with($lower, '_url') || str_ends_with($lower, '_link')) {
            return ['vo' => 'Url', 'flags' => ['rule' => 'url', 'normalize' => 'trim']];
        }

        if ($lower === 'uuid' || str_ends_with($lower, '_uuid')) {
            return ['vo' => 'Uuid', 'flags' => ['rule' => 'uuid']];
        }

        if ($lower === 'ip' || $lower === 'ip_address' || str_ends_with($lower, '_ip') || str_ends_with($lower, '_ip_address')) {
            return ['vo' => 'IpAddress', 'flags' => ['rule' => 'ip']];
        }

        if ($lower === 'slug' || str_ends_with($lower, '_slug')) {
            return [
                'vo' => 'Slug',
                'flags' => ['rule' => 'regex:/^[a-z0-9-]+$/', 'normalize' => 'lower'],
            ];
        }

        if ($lower === 'country_code') {
            return [
                'vo' => 'CountryCode',
                'flags' => ['rule' => 'regex:/^[A-Z]{2}$/', 'normalize' => 'upper'],
            ];
        }

        if ($lower === 'currency_code') {
            return [
                'vo' => 'CurrencyCode',
                'flags' => ['rule' => 'regex:/^[A-Z]{3}$/', 'normalize' => 'upper'],
            ];
        }

        if (str_ends_with($lower, '_amount_cents') || str_ends_with($lower, '_cents')) {
            return [
                'candidate' => true,
                'note' => 'Money column — see cknow/laravel-money. Aegis does not generate Money types.',
            ];
        }

        if ($lower === 'status' || str_ends_with($lower, '_status')) {
            return [
                'candidate' => true,
                'note' => 'Status column — provide --type=App\\Enums\\... pointing at your enum, then run make:value-object.',
            ];
        }

        return null;
    }
}
