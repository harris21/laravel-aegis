<?php

declare(strict_types=1);

use HarrisRafto\Aegis\Console\Generators\CastWirer;

it('inserts the cast into a multi-line casts() method', function () {
    $source = <<<'PHP'
<?php

namespace App\Models;

class Order
{
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
PHP;

    $result = CastWirer::wire($source, 'email', 'App\\Domain\\ValueObjects\\Email');

    expect($result['modified'])->toBeTrue();
    expect($result['alreadyPresent'])->toBeFalse();
    expect($result['source'])->toContain("'email' => \\App\\Domain\\ValueObjects\\Email::class,");
    expect($result['source'])->toContain("'created_at' => 'datetime',");
});

it('inserts the cast into an empty casts() array', function () {
    $source = <<<'PHP'
<?php

namespace App\Models;

class Order
{
    protected function casts(): array
    {
        return [];
    }
}
PHP;

    $result = CastWirer::wire($source, 'email', 'App\\Domain\\ValueObjects\\Email');

    expect($result['modified'])->toBeTrue();
    expect($result['source'])->toContain("'email' => \\App\\Domain\\ValueObjects\\Email::class,");
});

it('is idempotent when the column is already cast', function () {
    $source = <<<'PHP'
<?php

namespace App\Models;

class Order
{
    protected function casts(): array
    {
        return [
            'email' => \App\Domain\ValueObjects\Email::class,
        ];
    }
}
PHP;

    $result = CastWirer::wire($source, 'email', 'App\\Domain\\ValueObjects\\Email');

    expect($result['modified'])->toBeFalse();
    expect($result['alreadyPresent'])->toBeTrue();
    expect($result['source'])->toBe($source);
});

it('returns a manual fallback when the model has no casts() method', function () {
    $source = <<<'PHP'
<?php

namespace App\Models;

class Order
{
    // no casts() at all
}
PHP;

    $result = CastWirer::wire($source, 'email', 'App\\Domain\\ValueObjects\\Email');

    expect($result['manual'])->toBeTrue();
    expect($result['modified'])->toBeFalse();
    expect($result['source'])->toBe($source);
    expect($result['snippet'])->toBe("'email' => \\App\\Domain\\ValueObjects\\Email::class,");
});

it('adds a trailing comma to the previous last entry when missing', function () {
    $source = <<<'PHP'
<?php

namespace App\Models;

class Order
{
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime'
        ];
    }
}
PHP;

    $result = CastWirer::wire($source, 'email', 'App\\Domain\\ValueObjects\\Email');

    expect($result['modified'])->toBeTrue();
    expect($result['source'])->toContain("'created_at' => 'datetime',");
});
