<?php

declare(strict_types=1);

use HarrisRafto\Aegis\Concerns\ResolvesValueObjects;
use HarrisRafto\Aegis\Rules\ValueObjectRule;
use HarrisRafto\Aegis\Tests\Fixtures\FakeEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Anonymous FormRequest stand-in so we exercise the trait without setting up the
 * full HTTP routing pipeline. The trait only depends on rules() + validated(),
 * both of which FormRequest provides through its container-resolved validator.
 */
function makeRequest(array $input, array $rules): FormRequest
{
    return new class($input, $rules) extends FormRequest
    {
        use ResolvesValueObjects;

        public function __construct(public array $declaredInput, public array $declaredRules)
        {
            parent::__construct(query: $declaredInput, request: $declaredInput);
        }

        public function rules(): array
        {
            return $this->declaredRules;
        }

        public function authorize(): bool
        {
            return true;
        }
    };
}

function validate(FormRequest $request): FormRequest
{
    $factory = app('validator');
    $validator = $factory->make($request->declaredInput, $request->rules());
    $validator->validate();

    // FormRequest stores validated data via setContainer/setRedirector,
    // but for our purposes the trait reads validated() which delegates to the
    // resolved validator. Setting it directly is the minimal wiring.
    $request->setValidator($validator);

    return $request;
}

it('returns the constructed Value Object for a validated field', function () {
    $request = validate(makeRequest(
        input: ['email' => 'Harris@Example.COM'],
        rules: ['email' => ['required', Rule::valueObject(FakeEmail::class)]],
    ));

    $email = $request->valueObject('email');

    expect($email)->toBeInstanceOf(FakeEmail::class);
    expect($email->value)->toBe('harris@example.com');
});

it('memoises the resolved Value Object', function () {
    $request = validate(makeRequest(
        input: ['email' => 'harris@example.com'],
        rules: ['email' => ['required', Rule::valueObject(FakeEmail::class)]],
    ));

    expect($request->valueObject('email'))->toBe($request->valueObject('email'));
});

it('returns null for a nullable field whose value is null', function () {
    $request = validate(makeRequest(
        input: ['email' => null],
        rules: ['email' => ['nullable', Rule::valueObject(FakeEmail::class)]],
    ));

    expect($request->valueObject('email'))->toBeNull();
});

it('throws when the key has no rules', function () {
    $request = validate(makeRequest(
        input: ['something_else' => 'x'],
        rules: ['something_else' => ['string']],
    ));

    expect(fn () => $request->valueObject('email'))
        ->toThrow(InvalidArgumentException::class);
});

it('throws when the key has no Rule::valueObject() attached', function () {
    $request = validate(makeRequest(
        input: ['email' => 'harris@example.com'],
        rules: ['email' => ['email']],
    ));

    expect(fn () => $request->valueObject('email'))
        ->toThrow(InvalidArgumentException::class);
});
