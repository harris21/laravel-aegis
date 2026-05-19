<p align="center">
  <img src="art/logo.png" width="520" alt="Aegis for Laravel">
</p>

<p align="center">
  <strong>Aegis for Laravel</strong>
</p>

<p align="center">
  <em>[EE-jis]</em>
</p>

<p align="center">
  Scaffolding and validation helpers for Value Objects.
</p>

<p align="center">
  Wrap your primitives and validate them at the boundary in one Artisan command.
</p>

---

## Why Aegis

A string isn't an email until something validates it. An int isn't money until something tags its currency.

A Value Object fixes that. Its constructor accepts the input and produces a valid instance, or throws. Bad values never reach the rest of the system.

The catch: a Value Object that does its job is around 70 lines of PHP. `final readonly` class, validation in the constructor, normalization, an `equals()` method, the `Castable` block with `get`/`set`/`compare` for Eloquent. Typing that for every string a team wants to harden costs more than the string was costing.

Aegis writes those lines for you. One Artisan command produces the Value Object class with everything wired up, plus a Pest test stub. If you pass `--cast=Model.column`, it also patches the model. You write the methods that belong to your domain.

## About the name

In Greek mythology the *aegis* was Athena's shield, carried into battle to deflect what shouldn't reach what it protected. The package borrows the name because that's what the constructor of a Value Object does: accepts what's valid and refuses everything else.

## Requirements

- PHP 8.3+
- Laravel 13

## Installation

```bash
composer require harrisrafto/laravel-aegis
```

The service provider is registered automatically via Laravel's package auto-discovery.

Optional — publish the config to override the default namespace for generated Value Objects:

```bash
php artisan vendor:publish --tag=aegis-config
```

## Quick start

### Scaffold a Value Object

```bash
php artisan make:value-object Email \
    --rule=email \
    --normalize=lower \
    --method=domain:string \
    --cast=Order.email
```

Generates:

- `app/Domain/ValueObjects/Email.php` — `final readonly`, validated, normalized, with the Castable block wired for Eloquent. Implements `Stringable` and `JsonSerializable` for the application edges. Contains an empty `domain(): string` stub for you to fill in.
- `tests/Unit/EmailTest.php` — Pest stub awaiting your assertions.
- `app/Models/Order.php` — patched to add `'email' => Email::class` inside its `casts()` method, preserving the existing indentation.

### Validate with the same Value Object

```php
use Illuminate\Validation\Rule;

public function rules(): array
{
    return [
        'email' => ['required', Rule::valueObject(Email::class)],
    ];
}
```

Aegis registers `valueObject` as a macro on Laravel's `Illuminate\Validation\Rule`, so the call site reads identically to any other built-in rule (`Rule::in(...)`, `Rule::unique(...)`).

### Resolve the validated instance from a FormRequest

```php
use HarrisRafto\Aegis\Concerns\ResolvesValueObjects;

class StoreUserRequest extends FormRequest
{
    use ResolvesValueObjects;

    public function rules(): array
    {
        return [
            'email' => ['required', Rule::valueObject(Email::class)],
        ];
    }
}

// In your controller:
$email = $request->valueObject('email'); // an Email instance, already validated
```

### Scan your codebase for Value Object candidates

```bash
php artisan vo:scan
```

Aegis walks your Eloquent models and migrations, flags column names that match common patterns (email, url, uuid, country_code, slug, ip, status, money), and prints the `make:value-object` command you'd run for each one. A final line tells you how much of your codebase is already wrapped:

```
app/Models/Customer.php
  · billing_email   → php artisan make:value-object Email --rule=email --normalize=lower --cast=Customer.billing_email
  · country_code    → php artisan make:value-object CountryCode --rule=regex:/^[A-Z]{2}$/ --normalize=upper --cast=Customer.country_code
  · monthly_amount_cents  candidate — Money column, see cknow/laravel-money

Scanned 3 models, 16 columns total.
7 commands ready, 2 candidates need your input, 1 already wrapped.
Value Object coverage: 6%.
```

The scanner reads model `$fillable`, `$casts`, and `casts()` declarations, plus any `Schema::create` blocks in your migrations. It never touches your database. Pass `--json` for machine-readable output, `--no-cast` to omit the `--cast=Model.column` part of each suggestion, or `--path` and `--migrations-path` to point at non-standard directories.

## Flags

| Flag | Purpose |
|---|---|
| `--rule=NAME[:ARGS]` | Validation rule. One of `email`, `url`, `ip`, `uuid`, `alpha_num`, `alpha`, `numeric`, `regex:PATTERN`. |
| `--normalize=FN[,FN]` | Normalization. Compose with commas: `lower`, `upper`, `trim`. |
| `--type=PHP_TYPE` | Property type. Default `string`. Also accepts `int`, `float`, `bool`, or a fully qualified class name. |
| `--method=NAME[:RETURN_TYPE]` | Empty method stub. Repeatable. |
| `--cast=Model.column` | Adds the cast wiring to `app/Models/Model.php`. Safe to re-run; the cast is added once. |
| `--namespace=NS` | Override the configured default namespace. |
| `--no-test` | Skip the Pest test stub. |
| `--dry-run` | Print the files that would be written or changed without touching disk. |
| `--force` | Overwrite existing files. |

## Credits

Built by [Harris Raftopoulos](https://x.com/harrisrafto) for [Laravel Live Japan 2026](https://laravellive.jp/en).

YouTube: [@harrisrafto](https://youtube.com/@harrisrafto)

Companion to the talk *"Bulletproof Your Laravel Code with Value Objects"* and the example repository at [harris21/laravel-value-objects-examples](https://github.com/harris21/laravel-value-objects-examples).

Based on the Value Object pattern from Eric Evans' *Domain-Driven Design* and Martin Fowler's *Patterns of Enterprise Application Architecture*.

## License

MIT
