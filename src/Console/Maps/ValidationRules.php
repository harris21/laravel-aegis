<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Console\Maps;

use InvalidArgumentException;

/**
 * Maps a `--rule=` flag to an inline PHP expression that is truthy when the
 * value is INVALID and must be rejected. The generator wraps it in
 * `if (rejectIf) throw ...` so the expression composes cleanly even when it
 * contains operators with awkward precedence (like the regex check's `!== 1`).
 */
final class ValidationRules
{
    /**
     * @return array{rejectIf: string, imports: list<string>}
     */
    public static function resolve(string $rule): array
    {
        [$name, $args] = self::parse($rule);

        return match ($name) {
            'email' => [
                'rejectIf' => '! filter_var($value, FILTER_VALIDATE_EMAIL)',
                'imports' => [],
            ],
            'url' => [
                'rejectIf' => '! filter_var($value, FILTER_VALIDATE_URL)',
                'imports' => [],
            ],
            'ip' => [
                'rejectIf' => '! filter_var($value, FILTER_VALIDATE_IP)',
                'imports' => [],
            ],
            'uuid' => [
                'rejectIf' => '! Str::isUuid($value)',
                'imports' => ['Illuminate\\Support\\Str'],
            ],
            'alpha_num' => [
                'rejectIf' => '! ctype_alnum($value)',
                'imports' => [],
            ],
            'alpha' => [
                'rejectIf' => '! ctype_alpha($value)',
                'imports' => [],
            ],
            'numeric' => [
                'rejectIf' => '! is_numeric($value)',
                'imports' => [],
            ],
            'regex' => [
                'rejectIf' => self::regexExpression($args),
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

        return sprintf('preg_match(%s, $value) !== 1', var_export($pattern, true));
    }
}
