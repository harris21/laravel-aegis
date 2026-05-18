<?php

declare(strict_types=1);

use HarrisRafto\Aegis\Rules\ValueObjectRule;
use HarrisRafto\Aegis\Tests\Fixtures\FakeEmail;
use Illuminate\Validation\Rule;

it('registers the valueObject macro on Illuminate\\Validation\\Rule', function () {
    expect(Rule::hasMacro('valueObject'))->toBeTrue();
});

it('Rule::valueObject() returns a ValueObjectRule instance', function () {
    $rule = Rule::valueObject(FakeEmail::class);

    expect($rule)->toBeInstanceOf(ValueObjectRule::class);
    expect($rule->valueObjectClass)->toBe(FakeEmail::class);
});
