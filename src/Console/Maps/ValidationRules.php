<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Console\Maps;

use InvalidArgumentException;

/**
 * Maps a `--rule=` flag to the inline PHP expression that evaluates truthy
 * when the value is valid and falsy when it must be rejected.
 *
 * The generator wraps the expression in:
 *
 *     if (! ({expression})) {
 *         throw new InvalidArgumentException("Invalid {name}: {$value}");
 *     }
 */
final class ValidationRules
{
    /**
     * @return array{check: string, imports: list<string>}
     */
    public static function resolve(string $rule): array
    {
        [$name, $args] = self::parse($rule);

        return match ($name) {
            'email' => [
                'check' => 'filter_var($value, FILTER_VALIDATE_EMAIL)',
                'imports' => [],
            ],
            'url' => [
                'check' => 'filter_var($value, FILTER_VALIDATE_URL)',
                'imports' => [],
            ],
            'ip' => [
                'check' => 'filter_var($value, FILTER_VALIDATE_IP)',
                'imports' => [],
            ],
            'uuid' => [
                'check' => 'Str::isUuid($value)',
                'imports' => ['Illuminate\\Support\\Str'],
            ],
            'alpha_num' => [
                'check' => 'ctype_alnum($value)',
                'imports' => [],
            ],
            'alpha' => [
                'check' => 'ctype_alpha($value)',
                'imports' => [],
            ],
            'numeric' => [
                'check' => 'is_numeric($value)',
                'imports' => [],
            ],
            'regex' => [
                'check' => self::regexExpression($args),
                'imports' => [],
            ],
            default => throw new InvalidArgumentException(
                "Unknown validation rule: {$rule}. Supported: email, url, ip, uuid, alpha_num, alpha, numeric, regex:PATTERN."
            ),
        };
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private static function parse(string $rule): array
    {
        if (str_contains($rule, ':')) {
            [$name, $args] = explode(':', $rule, 2);

            return [$name, $args];
        }

        return [$rule, null];
    }

    private static function regexExpression(?string $pattern): string
    {
        if ($pattern === null || $pattern === '') {
            throw new InvalidArgumentException('--rule=regex requires a pattern (e.g. --rule=regex:/^[A-Z0-9]+$/).');
        }

        return sprintf('preg_match(%s, $value) === 1', var_export($pattern, true));
    }
}
