<?php

declare(strict_types=1);

use HarrisRafto\Aegis\Console\Generators\ValueObjectGenerator;

function lintPhp(string $source): bool
{
    $tmp = tempnam(sys_get_temp_dir(), 'aegis-lint-').'.php';
    file_put_contents($tmp, $source);

    $output = [];
    $exitCode = 0;
    exec("php -l {$tmp} 2>&1", $output, $exitCode);

    @unlink($tmp);

    return $exitCode === 0;
}

function loadGenerated(string $source, string $fqcn): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'aegis-vo-').'.php';
    file_put_contents($tmp, $source);

    require_once $tmp;

    return $fqcn;
}

it('generates syntactically valid PHP for the canonical Email shape', function () {
    $source = (new ValueObjectGenerator(
        name: 'Email',
        namespace: 'HarrisRafto\\Aegis\\Tests\\Generated\\Canonical',
        propertyType: 'string',
        rule: 'email',
        normalizers: ['lower'],
        methods: [['name' => 'domain', 'return' => 'string']],
    ))->generate();

    expect(lintPhp($source))->toBeTrue();
});

it('generates a working Email Value Object', function () {
    $source = (new ValueObjectGenerator(
        name: 'Email',
        namespace: 'HarrisRafto\\Aegis\\Tests\\Generated\\Behavior',
        propertyType: 'string',
        rule: 'email',
        normalizers: ['lower'],
        methods: [],
    ))->generate();

    $fqcn = loadGenerated($source, 'HarrisRafto\\Aegis\\Tests\\Generated\\Behavior\\Email');

    $valid = new $fqcn('Harris@Example.COM');
    expect($valid->value)->toBe('harris@example.com');

    expect(fn () => new $fqcn('not-an-email'))->toThrow(InvalidArgumentException::class);

    $a = new $fqcn('Harris@Example.com');
    $b = new $fqcn('harris@example.com');
    expect($a->equals($b))->toBeTrue();

    expect((string) $valid)->toBe('harris@example.com');
    expect(json_encode($valid))->toBe('"harris@example.com"');
});

it('includes ComparesCastableAttributes when normalize is set', function () {
    $source = (new ValueObjectGenerator(
        name: 'WithNormalize',
        namespace: 'HarrisRafto\\Aegis\\Tests\\Generated\\WithNorm',
        propertyType: 'string',
        rule: 'email',
        normalizers: ['lower'],
        methods: [],
    ))->generate();

    expect($source)->toContain('ComparesCastableAttributes');
    expect($source)->toContain('public function compare(');
});

it('omits ComparesCastableAttributes when no normalize is set', function () {
    $source = (new ValueObjectGenerator(
        name: 'NoNorm',
        namespace: 'HarrisRafto\\Aegis\\Tests\\Generated\\NoNorm',
        propertyType: 'string',
        rule: 'email',
        normalizers: [],
        methods: [],
    ))->generate();

    expect($source)->not->toContain('ComparesCastableAttributes');
    expect($source)->not->toContain('public function compare(');
});

it('emits empty method stubs with the requested return type', function () {
    $source = (new ValueObjectGenerator(
        name: 'WithStubs',
        namespace: 'HarrisRafto\\Aegis\\Tests\\Generated\\Stubs',
        propertyType: 'string',
        rule: null,
        normalizers: [],
        methods: [
            ['name' => 'domain', 'return' => 'string'],
            ['name' => 'isCompany', 'return' => 'bool'],
            ['name' => 'noType', 'return' => null],
        ],
    ))->generate();

    expect($source)
        ->toContain('public function domain(): string')
        ->toContain('public function isCompany(): bool')
        ->toContain('public function noType()');
});

it('skips validation block when no rule is provided', function () {
    $source = (new ValueObjectGenerator(
        name: 'Plain',
        namespace: 'HarrisRafto\\Aegis\\Tests\\Generated\\Plain',
        propertyType: 'string',
        rule: null,
        normalizers: [],
        methods: [],
    ))->generate();

    expect($source)->not->toContain('throw new InvalidArgumentException');
    expect(lintPhp($source))->toBeTrue();
});

it('handles regex rules with the user pattern verbatim', function () {
    $source = (new ValueObjectGenerator(
        name: 'CouponCode',
        namespace: 'HarrisRafto\\Aegis\\Tests\\Generated\\Regex',
        propertyType: 'string',
        rule: 'regex:/^[A-Z0-9]+$/',
        normalizers: [],
        methods: [],
    ))->generate();

    expect($source)->toContain("preg_match('/^[A-Z0-9]+\$/', \$value)");
    expect(lintPhp($source))->toBeTrue();
});
