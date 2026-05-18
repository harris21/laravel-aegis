<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Console\Generators;

use HarrisRafto\Aegis\Console\Maps\Normalizers;
use HarrisRafto\Aegis\Console\Maps\ValidationRules;

/**
 * Builds the PHP source for a generated Value Object.
 *
 * The property is declared, not constructor-promoted, so normalization can
 * reassign $value after validation. compare() is emitted only when --normalize
 * is present, because that is when dirty checking needs value equality.
 */
final class ValueObjectGenerator
{
    private const PRIMITIVE_TYPES = ['string', 'int', 'float', 'bool', 'mixed'];

    /**
     * @param  list<string>                              $normalizers  e.g. ['trim', 'lower']
     * @param  list<array{name: string, return: ?string}> $methods      user-defined --method stubs
     */
    public function __construct(
        private readonly string $name,
        private readonly string $namespace,
        private readonly string $propertyType,
        private readonly ?string $rule,
        private readonly array $normalizers,
        private readonly array $methods,
    ) {}

    public function generate(): string
    {
        $imports = $this->collectImports();
        $useBlock = $this->renderUseBlock($imports);
        $propertyType = $this->shortType($this->propertyType);

        $constructor = $this->renderConstructor();
        $equals = $this->renderEquals();
        $methodStubs = $this->renderMethodStubs();
        $castUsing = $this->renderCastUsing();

        $methodBlock = $methodStubs === '' ? '' : "\n{$methodStubs}";

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespace};

{$useBlock}

final readonly class {$this->name} implements Castable, Stringable, JsonSerializable
{
    public {$propertyType} \$value;

{$constructor}

{$equals}
{$methodBlock}
    public function __toString(): string
    {
        return (string) \$this->value;
    }

    public function jsonSerialize(): mixed
    {
        return \$this->value;
    }

{$castUsing}
}

PHP;
    }

    /**
     * @return list<string>
     */
    private function collectImports(): array
    {
        $imports = [
            'Illuminate\\Contracts\\Database\\Eloquent\\Castable',
            'Illuminate\\Contracts\\Database\\Eloquent\\CastsAttributes',
            'Illuminate\\Database\\Eloquent\\Model',
            'InvalidArgumentException',
            'JsonSerializable',
            'Stringable',
        ];

        if ($this->normalizers !== []) {
            $imports[] = 'Illuminate\\Contracts\\Database\\Eloquent\\ComparesCastableAttributes';
        }

        if ($this->rule !== null) {
            $imports = [...$imports, ...ValidationRules::resolve($this->rule)['imports']];
        }

        if (! $this->isPrimitive($this->propertyType) && str_contains($this->propertyType, '\\')) {
            $imports[] = ltrim($this->propertyType, '\\');
        }

        $imports = array_values(array_unique($imports));
        sort($imports);

        return $imports;
    }

    /**
     * @param  list<string>  $imports
     */
    private function renderUseBlock(array $imports): string
    {
        return implode("\n", array_map(static fn (string $fqcn): string => "use {$fqcn};", $imports));
    }

    private function renderConstructor(): string
    {
        $body = [];

        if ($this->rule !== null) {
            $body[] = $this->renderValidation();
        }

        foreach (Normalizers::resolveAll($this->normalizers) as $normalizeLine) {
            $body[] = '        '.$normalizeLine;
        }

        $body[] = '        $this->value = $value;';

        $bodyString = implode("\n\n", $body);
        $paramType = $this->shortType($this->propertyType);

        return <<<PHP
    public function __construct({$paramType} \$value)
    {
{$bodyString}
    }
PHP;
    }

    private function renderValidation(): string
    {
        $resolved = ValidationRules::resolve($this->rule);
        $name = strtolower($this->name);

        return <<<PHP
        if (! ({$resolved['check']})) {
            throw new InvalidArgumentException("Invalid {$name}: {\$value}");
        }
PHP;
    }

    private function renderEquals(): string
    {
        return <<<PHP
    public function equals(self \$other): bool
    {
        return \$this->value === \$other->value;
    }
PHP;
    }

    private function renderMethodStubs(): string
    {
        if ($this->methods === []) {
            return '';
        }

        $rendered = array_map(function (array $method): string {
            $signature = $method['return'] !== null
                ? "public function {$method['name']}(): {$method['return']}"
                : "public function {$method['name']}()";

            return <<<PHP
    {$signature}
    {
        //
    }
PHP;
        }, $this->methods);

        return implode("\n\n", $rendered)."\n";
    }

    private function renderCastUsing(): string
    {
        $hasNormalize = $this->normalizers !== [];
        $interfaces = $hasNormalize
            ? 'CastsAttributes, ComparesCastableAttributes'
            : 'CastsAttributes';

        $setReturn = $this->isPrimitive($this->propertyType)
            ? '?'.$this->shortType($this->propertyType)
            : 'mixed';

        $compareBlock = $hasNormalize ? "\n\n".$this->renderCompareMethod() : '';

        $name = $this->name;

        return <<<PHP
    public static function castUsing(array \$arguments): CastsAttributes
    {
        return new class implements {$interfaces}
        {
            public function get(Model \$model, string \$key, mixed \$value, array \$attributes): ?{$name}
            {
                return \$value ? new {$name}(\$value) : null;
            }

            public function set(Model \$model, string \$key, mixed \$value, array \$attributes): {$setReturn}
            {
                if (\$value === null) {
                    return null;
                }

                return (\$value instanceof {$name} ? \$value : new {$name}(\$value))->value;
            }{$compareBlock}
        };
    }
PHP;
    }

    private function renderCompareMethod(): string
    {
        $name = $this->name;

        return <<<PHP
            public function compare(Model \$model, string \$key, mixed \$firstValue, mixed \$secondValue): bool
            {
                if (\$firstValue === null || \$secondValue === null) {
                    return \$firstValue === \$secondValue;
                }

                return (new {$name}((string) \$firstValue))->equals(new {$name}((string) \$secondValue));
            }
PHP;
    }

    private function isPrimitive(string $type): bool
    {
        return in_array(strtolower($type), self::PRIMITIVE_TYPES, true);
    }

    private function shortType(string $type): string
    {
        if ($this->isPrimitive($type)) {
            return strtolower($type);
        }

        $fqcn = ltrim($type, '\\');

        return str_contains($fqcn, '\\')
            ? substr($fqcn, (int) strrpos($fqcn, '\\') + 1)
            : $fqcn;
    }
}
