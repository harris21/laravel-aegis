<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Console;

use HarrisRafto\Aegis\Console\Scanner\Scanner;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

final class ScanCommand extends Command
{
    /** @var string */
    protected $signature = 'vo:scan
                            {--path=app/Models : Directory of Eloquent models to walk}
                            {--migrations-path=database/migrations : Directory of migrations to walk; empty to skip}
                            {--no-cast : Omit --cast=Model.column from each suggestion}
                            {--json : Output the report as JSON instead of formatted text}';

    /** @var string */
    protected $description = 'Walk Eloquent models and migrations, suggesting columns that would benefit from a Value Object.';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $modelsPath = $this->resolvePath((string) $this->option('path'));
        $migrationsRaw = (string) $this->option('migrations-path');
        $migrationsPath = $migrationsRaw === '' ? null : $this->resolvePath($migrationsRaw);

        $report = (new Scanner($this->files))->scan($modelsPath, $migrationsPath);

        if ($this->option('json') === true) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->renderText($report);

        return self::SUCCESS;
    }

    /**
     * @param  array{models: list<array{class: string, table: string, file: string, columns: list<string>, suggestions: list<array{column: string, result: array<string,mixed>}>, wrapped: list<string>}>, stats: array{modelCount: int, columnCount: int, suggestionCount: int, candidateCount: int, wrappedCount: int}}  $report
     */
    private function renderText(array $report): void
    {
        $omitCast = $this->option('no-cast') === true;

        foreach ($report['models'] as $model) {
            if ($model['suggestions'] === []) {
                continue;
            }

            $this->line('');
            $this->line("<options=bold>{$model['file']}</>");

            foreach ($model['suggestions'] as $suggestion) {
                $line = $this->formatSuggestion($model['class'], $suggestion, $omitCast);
                $this->line($line);
            }
        }

        $this->renderStats($report['stats']);
    }

    /**
     * @param  array{column: string, result: array<string,mixed>}  $suggestion
     */
    private function formatSuggestion(string $modelClass, array $suggestion, bool $omitCast): string
    {
        $column = $suggestion['column'];
        $result = $suggestion['result'];

        if (isset($result['candidate'])) {
            return "  · <fg=yellow>{$column}</>  candidate — {$result['note']}";
        }

        $command = $this->buildMakeCommand($modelClass, $column, $result, $omitCast);

        return "  · <fg=green>{$column}</>  → {$command}";
    }

    /**
     * @param  array{vo: string, flags: array<string,string>}  $result
     */
    private function buildMakeCommand(string $modelClass, string $column, array $result, bool $omitCast): string
    {
        $parts = ['php artisan make:value-object', $result['vo']];

        foreach ($result['flags'] as $name => $value) {
            $parts[] = "--{$name}={$value}";
        }

        if (! $omitCast) {
            $parts[] = "--cast={$modelClass}.{$column}";
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array{modelCount: int, columnCount: int, suggestionCount: int, candidateCount: int, wrappedCount: int}  $stats
     */
    private function renderStats(array $stats): void
    {
        $this->line('');

        $models = $stats['modelCount'];
        $columns = $stats['columnCount'];
        $suggestions = $stats['suggestionCount'];
        $candidates = $stats['candidateCount'];
        $wrapped = $stats['wrappedCount'];

        $coverage = $columns > 0
            ? (int) round($wrapped / $columns * 100)
            : 0;

        $this->line("Scanned <options=bold>{$models}</> models, <options=bold>{$columns}</> columns total.");
        $this->line("<fg=green>{$suggestions}</> commands ready, <fg=yellow>{$candidates}</> candidates need your input, <fg=cyan>{$wrapped}</> already wrapped.");
        $this->line("Value Object coverage: <options=bold>{$coverage}%</>.");
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
