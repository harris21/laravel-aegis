<?php

declare(strict_types=1);

use HarrisRafto\Aegis\Console\Scanner\ColumnExtractor;

it('extracts the class name', function () {
    $source = '<?php namespace App\Models; class User extends Model {}';
    expect(ColumnExtractor::fromSource($source)['class'])->toBe('User');
});

it('extracts the table name when declared', function () {
    $source = <<<'PHP'
<?php
class Order extends Model
{
    protected $table = 'shop_orders';
}
PHP;
    expect(ColumnExtractor::fromSource($source)['table'])->toBe('shop_orders');
});

it('returns null table when not declared', function () {
    $source = '<?php class User extends Model {}';
    expect(ColumnExtractor::fromSource($source)['table'])->toBeNull();
});

it('extracts columns from $fillable', function () {
    $source = <<<'PHP'
<?php
class User extends Model
{
    protected $fillable = ['name', 'email', 'password'];
}
PHP;

    expect(ColumnExtractor::fromSource($source)['columns'])->toBe(['name', 'email', 'password']);
});

it('extracts columns from the casts() method', function () {
    $source = <<<'PHP'
<?php
class User extends Model
{
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'email' => Email::class,
        ];
    }
}
PHP;

    $result = ColumnExtractor::fromSource($source);

    expect($result['columns'])->toContain('email_verified_at')
        ->toContain('is_admin')
        ->toContain('email');
});

it('extracts columns from the legacy $casts property', function () {
    $source = <<<'PHP'
<?php
class User extends Model
{
    protected $casts = [
        'is_admin' => 'boolean',
        'metadata' => 'array',
    ];
}
PHP;

    expect(ColumnExtractor::fromSource($source)['columns'])
        ->toContain('is_admin')
        ->toContain('metadata');
});

it('flags columns whose cast already points at a class as wrapped', function () {
    $source = <<<'PHP'
<?php
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'email' => \App\Domain\ValueObjects\Email::class,
            'status' => Status::class,
            'created_at' => 'datetime',
        ];
    }
}
PHP;

    $result = ColumnExtractor::fromSource($source);

    expect($result['wrappedCasts'])->toHaveKey('email');
    expect($result['wrappedCasts'])->toHaveKey('status');
    expect($result['wrappedCasts'])->not->toHaveKey('created_at');
});

it('unions $fillable and casts() into a deduplicated column list', function () {
    $source = <<<'PHP'
<?php
class User extends Model
{
    protected $fillable = ['email', 'name'];

    protected function casts(): array
    {
        return [
            'email' => 'string',
            'is_admin' => 'boolean',
        ];
    }
}
PHP;

    $columns = ColumnExtractor::fromSource($source)['columns'];

    expect($columns)->toContain('email')
        ->toContain('name')
        ->toContain('is_admin');
    expect(count($columns))->toBe(3);
});
