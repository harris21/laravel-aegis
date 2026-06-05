<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;
use Throwable;
use TypeError;

/**
 * Treats any Value Object class as a Laravel validation rule.
 *
 * The rule constructs a new instance of the Value Object with the candidate
 * value. If the constructor throws (for any reason — validation, normalization,
 * a missing dependency), the rule fails with the thrown message.
 *
 * Value Objects can opt out of leaking exception text to end users by defining
 * a static `validationMessage(string $value, \Throwable $exception): string`
 * method. When present, its return is used instead of the raw exception message;
 * Laravel's `:attribute` substitution still applies.
 */
final class ValueObjectRule implements ValidationRule
{
    public function __construct(
        public readonly string $valueObjectClass,
    ) {
        if (! class_exists($valueObjectClass)) {
            throw new InvalidArgumentException(
                "Value Object class does not exist: {$valueObjectClass}"
            );
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            new ($this->valueObjectClass)($value);
        } catch (TypeError $e) {
            // Wrong type supplied (e.g. array passed to a scalar-backed VO).
            // Fall back to a generic message so the raw TypeError is not leaked to the user.
            $fail($this->resolveMessage($value, $e, 'The :attribute field is invalid.'));
        } catch (Throwable $e) {
            // Surface the VO's own message (or its validationMessage() override).
            $fail($this->resolveMessage($value, $e));
        }
    }

    private function resolveMessage(mixed $value, Throwable $exception, string $default = ''): string
    {
        if (method_exists($this->valueObjectClass, 'validationMessage')) {
            /** @var callable(string, Throwable): string $callable */
            $callable = [$this->valueObjectClass, 'validationMessage'];

            return $callable(is_scalar($value) ? (string) $value : '', $exception);
        }

        return $default !== '' ? $default : $exception->getMessage();
    }
}
