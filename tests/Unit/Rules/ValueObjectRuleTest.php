<?php

declare(strict_types=1);

use HarrisRafto\Aegis\Rules\ValueObjectRule;
use HarrisRafto\Aegis\Tests\Fixtures\FakeEmail;
use HarrisRafto\Aegis\Tests\Fixtures\FakeEmailWithCustomMessage;

it('passes when the Value Object constructor accepts the value', function () {
    $rule = new ValueObjectRule(FakeEmail::class);

    $failed = false;
    $fail = function () use (&$failed) {
        $failed = true;
    };

    $rule->validate('email', 'harris@example.com', $fail);

    expect($failed)->toBeFalse();
});

it('fails with the exception message when the constructor throws', function () {
    $rule = new ValueObjectRule(FakeEmail::class);

    $message = null;
    $fail = function (string $msg) use (&$message) {
        $message = $msg;
    };

    $rule->validate('email', 'not-an-email', $fail);

    expect($message)->toBe('Invalid email: not-an-email');
});

it('uses the validationMessage() static method when present', function () {
    $rule = new ValueObjectRule(FakeEmailWithCustomMessage::class);

    $message = null;
    $fail = function (string $msg) use (&$message) {
        $message = $msg;
    };

    $rule->validate('email', 'not-an-email', $fail);

    expect($message)->toBe('The :attribute must be a valid email address.');
});

it('throws when the Value Object class does not exist', function () {
    expect(fn () => new ValueObjectRule('App\\NoSuchClass'))
        ->toThrow(InvalidArgumentException::class);
});

it('exposes the Value Object class on the rule instance', function () {
    $rule = new ValueObjectRule(FakeEmail::class);

    expect($rule->valueObjectClass)->toBe(FakeEmail::class);
});

it('fails with a scalar message when a non-scalar value is passed', function () {
    $rule = new ValueObjectRule(FakeEmail::class);

    $message = null;
    $fail = function (string $msg) use (&$message) {
        $message = $msg;
    };

    $rule->validate('email', ['not', 'a', 'string'], $fail);

    expect($message)->toBe('The email field must be a scalar value.');
});

it('forwards integer scalar to the Value Object constructor rather than blocking it early', function () {
    $rule = new ValueObjectRule(FakeEmail::class);

    $message = null;
    $fail = function (string $msg) use (&$message) {
        $message = $msg;
    };

    $rule->validate('email', 42, $fail);

    expect($message)->not->toBe('The email field must be a scalar value.')
        ->not->toBeNull();
});
