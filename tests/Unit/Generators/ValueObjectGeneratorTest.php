<?php

declare(strict_types=1);

use HarrisRafto\Aegis\Console\Generators\ValueObjectGenerator;

it('generates syntactically valid PHP for the canonical Email shape', function () {
    $source = (new ValueObjectGenerator(
        name: 'Email',
        namespace: 'HarrisRafto\\Aegis\\Tests\\Generated\\Canonical',
        propertyType: 'string',
        rule: 'email',
        normalizers: ['lower'],
        methods: [['name' => 'domain', 'return' => 'string']],
    ))->generate();

    expect($source)
        ->toContain('<?php')
        ->toContain('declare(strict_types=1);')
        ->toContain('namespace HarrisRafto\\Aegis\\Tests\\Generated\\Canonical;')
        ->toContain('final readonly class Email implements Castable, Stringable, JsonSerializable')
        ->toContain('public string $value;')
        ->toContain('if (! (filter_var($value, FILTER_VALIDATE_EMAIL)))')
        ->toContain('$value = mb_strtolower($value);')
        ->toContain('$this->value = $value;')
        ->toContain('public function equals(self $other): bool')
        ->toContain('public function domain(): string')
        ->toContain('public function __toString(): string')
        ->toContain('public function jsonSerialize(): mixed')
        ->toContain('public static function castUsing(array $arguments): CastsAttributes')
        ->toContain('ComparesCastableAttributes');
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

    expect($source)
        ->toContain('ComparesCastableAttributes')
        ->toContain('public function compare(');
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

    expect($source)
        ->not->toContain('ComparesCastableAttributes')
        ->not->toContain('public function compare(');
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
});
