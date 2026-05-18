<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Tests\Fixtures;

use InvalidArgumentException;

final readonly class FakeEmail
{
    public string $value;

    public function __construct(string $value)
    {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email: {$value}");
        }

        $this->value = mb_strtolower($value);
    }
}
