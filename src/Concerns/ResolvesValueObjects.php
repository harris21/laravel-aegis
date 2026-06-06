<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Concerns;

use HarrisRafto\Aegis\Rules\ValueObjectRule;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * Adds `$request->valueObject($key)` to a FormRequest, returning the validated
 * Value Object instance for a field whose `rules()` entry contains a
 * `Rule::valueObject(...)` call.
 *
 * The trait introspects `rules()` to discover which fields are Value Object
 * fields, constructs the Value Object from the *validated* input (so the value
 * has already passed every other rule attached to it), and memoises the result
 * for subsequent calls within the same request lifecycle.
 *
 * If the field is nullable and missing/null in validated input, the trait
 * returns `null`.
 */
trait ResolvesValueObjects
{
    /**
     * @var array<string, object|null>
     */
    private array $resolvedValueObjects = [];

    public function valueObject(string $key): ?object
    {
        if (array_key_exists($key, $this->resolvedValueObjects)) {
            return $this->resolvedValueObjects[$key];
        }

        $rule = $this->locateValueObjectRule($key);
        $validated = $this->validated();
        $value = Arr::get($validated, $key);

        if ($value === null) {
            return $this->resolvedValueObjects[$key] = null;
        }

        $class = $rule->valueObjectClass;

        return $this->resolvedValueObjects[$key] = new $class($value);
    }

    private function locateValueObjectRule(string $key): ValueObjectRule
    {
        $rules = method_exists($this, 'rules') ? $this->rules() : [];

        if (! is_array($rules) || ! array_key_exists($key, $rules)) {
            throw new InvalidArgumentException(
                "No rules defined for key '{$key}'. ".
                'ResolvesValueObjects requires the field to be declared in rules().'
            );
        }

        $entries = is_array($rules[$key]) ? $rules[$key] : [$rules[$key]];

        foreach ($entries as $entry) {
            if ($entry instanceof ValueObjectRule) {
                return $entry;
            }
        }

        throw new InvalidArgumentException(
            "Field '{$key}' has no Rule::valueObject(...) rule attached. ".
            'Add one to its rules() entry before calling $request->valueObject().'
        );
    }
}
