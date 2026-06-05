<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Tests\Fixtures;

use InvalidArgumentException;

final readonly class FakeNonScalarValue
{
    public array $items;

    public function __construct(mixed $value)
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('Expected an array.');
        }

        $this->items = $value;
    }
}
