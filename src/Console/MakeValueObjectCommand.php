<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Console;

use HarrisRafto\Aegis\Console\Generators\CastWirer;
use HarrisRafto\Aegis\Console\Generators\TestGenerator;
use HarrisRafto\Aegis\Console\Generators\ValueObjectGenerator;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use RuntimeException;

final class MakeValueObjectCommand extends Command
{
    /** @var string */
    protected $signature = 'make:value-object
                            {name : The PascalCase name of the Value Object class}
                            {--rule= : Validation rule (email, url, ip, uuid, alpha_num, alpha, numeric, regex:PATTERN)}
                            {--normalize= : Comma-separated normalizers (lower, upper, trim)}
                            {--type=string : PHP type for the $value property}
                            {--method=* : Empty method stubs as name[:returnType], repeatable}
                            {--cast= : Wire the cast into a model: --cast=Model.column}
                            {--namespace= : Override the configured default namespace}
                            {--no-test : Skip the Pest test stub}
                            {--dry-run : Print the planned changes; write nothing}
                            {--force : Overwrite existing files}';

    /** @var string */
    protected $description = 'Scaffold a Value Object class, Pest test stub, and (optionally) the Eloquent cast wiring.';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            return $this->scaffold();
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function scaffold(): int
    {
        $name = $this->resolveName();
        $namespace = $this->resolveNamespace();
        $type = (string) $this->option('type');
        $rule = $this->resolveRule();
        $normalizers = $this->resolveNormalizers();
        $methods = $this->resolveMethods();

        $voSource = (new ValueObjectGenerator(
            name: $name,
            namespace: $namespace,
            propertyType: $type,
            rule: $rule,
            normalizers: $normalizers,
            methods: $methods,
        ))->generate();

        $voPath = $this->resolveValueObjectPath($namespace, $name);
        $writeVo = $this->planFileWrite($voPath, $voSource);

        $testPlan = $this->option('no-test') === true
            ? null
            : $this->planTestWrite($name, $namespace);

        $castPlan = $this->option('cast') !== null
            ? $this->planCast((string) $this->option('cast'), $namespace, $name)
            : null;

        // Dry-run reports the plan and exits.
        if ($this->option('dry-run') === true) {
            $this->reportPlan($writeVo, $testPlan, $castPlan);

            return self::SUCCESS;
        }

        // Apply.
        $this->applyFileWrite($writeVo);

        if ($testPlan !== null) {
            $this->applyFileWrite($testPlan);
        }

        if ($castPlan !== null) {
            $this->applyCast($castPlan);
        }

        $this->newLine();
        $this->components->info('Value Object scaffolded.');

        return self::SUCCESS;
    }

    // ----------------------------------------------------------------------
    //  Input resolution
    // ----------------------------------------------------------------------

    private function resolveName(): string
    {
        $raw = (string) $this->argument('name');

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $raw) !== 1) {
            throw new InvalidArgumentException(
                "The Value Object name must be PascalCase (got: {$raw})."
            );
        }

        return $raw;
    }

    private function resolveNamespace(): string
    {
        $override = $this->option('namespace');

        if (is_string($override) && $override !== '') {
            return trim($override, '\\');
        }

        $configured = config('aegis.namespace');

        return is_string($configured) ? trim($configured, '\\') : 'App\\Domain\\ValueObjects';
    }

    private function resolveRule(): ?string
    {
        $rule = $this->option('rule');

        return is_string($rule) && $rule !== '' ? $rule : null;
    }

    /**
     * @return list<string>
     */
    private function resolveNormalizers(): array
    {
        $raw = $this->option('normalize');

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * @return list<array{name: string, return: ?string}>
     */
    private function resolveMethods(): array
    {
        $raw = $this->option('method');
        $methods = is_array($raw) ? $raw : [];

        return array_values(array_map(static function (string $spec): array {
            $spec = trim($spec);

            if (! str_contains($spec, ':')) {
                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $spec) !== 1) {
                    throw new InvalidArgumentException(
                        "Invalid --method specification: {$spec}. Expected name or name:returnType."
                    );
                }

                return ['name' => $spec, 'return' => null];
            }

            [$name, $return] = explode(':', $spec, 2);

            return ['name' => trim($name), 'return' => trim($return)];
        }, $methods));
    }

    // ----------------------------------------------------------------------
    //  Path resolution
    // ----------------------------------------------------------------------

    private function resolveValueObjectPath(string $namespace, string $name): string
    {
        return $this->namespaceToPath($namespace).DIRECTORY_SEPARATOR.$name.'.php';
    }

    private function resolveTestPath(string $name): string
    {
        return base_path('tests/Unit/'.$name.'Test.php');
    }

    private function resolveModelPath(string $model): string
    {
        return app_path('Models/'.$model.'.php');
    }

    private function namespaceToPath(string $namespace): string
    {
        $configuredPath = config('aegis.path');

        if (is_string($configuredPath) && $configuredPath !== '') {
            return rtrim(self::resolveBase($configuredPath), DIRECTORY_SEPARATOR);
        }

        if (str_starts_with($namespace, 'App\\')) {
            $relative = substr($namespace, 4); // strip "App\"

            return app_path(str_replace('\\', DIRECTORY_SEPARATOR, $relative));
        }

        throw new InvalidArgumentException(
            "Cannot derive a file path from namespace '{$namespace}'. "
            ."Set `path` in config/aegis.php or pass --namespace=App\\..."
        );
    }

    private static function resolveBase(string $path): string
    {
        // Absolute path (POSIX or Windows-drive form) — pass through unchanged.
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    // ----------------------------------------------------------------------
    //  Plan & apply
    // ----------------------------------------------------------------------

    /**
     * @return array{path: string, contents: string, kind: string}
     */
    private function planFileWrite(string $path, string $contents): array
    {
        return [
            'path' => $path,
            'contents' => $contents,
            'kind' => 'create',
        ];
    }

    /**
     * @return array{path: string, contents: string, kind: string}
     */
    private function planTestWrite(string $name, string $namespace): array
    {
        $testSource = (new TestGenerator($name, $namespace))->generate();
        $testPath = $this->resolveTestPath($name);

        return $this->planFileWrite($testPath, $testSource);
    }

    /**
     * @return array{path: string, original: string, modified: string, alreadyPresent: bool, column: string, vo: string}
     */
    private function planCast(string $castSpec, string $namespace, string $name): array
    {
        if (! str_contains($castSpec, '.')) {
            throw new InvalidArgumentException(
                "Invalid --cast value: {$castSpec}. Expected Model.column (e.g. Order.email)."
            );
        }

        [$model, $column] = explode('.', $castSpec, 2);
        $modelPath = $this->resolveModelPath($model);

        if (! $this->files->exists($modelPath)) {
            throw new RuntimeException(
                "Model file not found: {$modelPath}. Aegis can only wire casts into existing models."
            );
        }

        $original = $this->files->get($modelPath);
        $voFqcn = $namespace.'\\'.$name;
        $result = CastWirer::wire($original, $column, $voFqcn);

        return [
            'path' => $modelPath,
            'original' => $original,
            'modified' => $result['source'],
            'alreadyPresent' => $result['alreadyPresent'],
            'column' => $column,
            'vo' => $voFqcn,
        ];
    }

    /**
     * @param  array{path: string, contents: string, kind: string}  $plan
     */
    private function applyFileWrite(array $plan): void
    {
        if ($this->files->exists($plan['path']) && $this->option('force') !== true) {
            throw new RuntimeException(
                "{$plan['path']} already exists. Pass --force to overwrite."
            );
        }

        $this->files->ensureDirectoryExists(dirname($plan['path']));
        $this->files->put($plan['path'], $plan['contents']);

        $this->components->twoColumnDetail(
            $plan['kind'] === 'create' ? '<fg=green>Created</>' : '<fg=blue>Updated</>',
            $this->relative($plan['path']),
        );
    }

    /**
     * @param  array{path: string, original: string, modified: string, alreadyPresent: bool, column: string, vo: string}  $plan
     */
    private function applyCast(array $plan): void
    {
        if ($plan['alreadyPresent']) {
            $this->components->twoColumnDetail(
                '<fg=yellow>Skipped</>',
                $this->relative($plan['path']).' (cast already present)',
            );

            return;
        }

        $this->files->put($plan['path'], $plan['modified']);

        $this->components->twoColumnDetail(
            '<fg=blue>Updated</>',
            $this->relative($plan['path'])." (+cast '{$plan['column']}')",
        );
    }

    /**
     * @param  array{path: string, contents: string, kind: string}                                                                   $voPlan
     * @param  array{path: string, contents: string, kind: string}|null                                                              $testPlan
     * @param  array{path: string, original: string, modified: string, alreadyPresent: bool, column: string, vo: string}|null  $castPlan
     */
    private function reportPlan(array $voPlan, ?array $testPlan, ?array $castPlan): void
    {
        $this->components->info('Dry run — no files written.');

        $this->components->twoColumnDetail('<fg=green>Would create</>', $this->relative($voPlan['path']));

        if ($testPlan !== null) {
            $this->components->twoColumnDetail('<fg=green>Would create</>', $this->relative($testPlan['path']));
        }

        if ($castPlan !== null) {
            $label = $castPlan['alreadyPresent']
                ? '<fg=yellow>Would skip (already cast)</>'
                : '<fg=blue>Would update</>';

            $this->components->twoColumnDetail(
                $label,
                $this->relative($castPlan['path'])." (+cast '{$castPlan['column']}')",
            );
        }
    }

    private function relative(string $path): string
    {
        $base = base_path();

        return str_starts_with($path, $base)
            ? ltrim(substr($path, strlen($base)), DIRECTORY_SEPARATOR)
            : $path;
    }
}
