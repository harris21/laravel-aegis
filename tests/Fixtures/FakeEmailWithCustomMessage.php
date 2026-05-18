<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Tests\Fixtures;

use InvalidArgumentException;
use Throwable;

final readonly class FakeEmailWithCustomMessage
{
    public string $value;

    public function __construct(string $value)
    {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email: {$value}");
        }

        $this->value = mb_strtolower($value);
    }

    public static function validationMessage(string $value, Throwable $exception): string
    {
        return 'The :attribute must be a valid email address.';
    }
}
