<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Console\Scanner;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Orchestrates the model + migration scan into a single report.
 *
 * The scanner takes filesystem paths, the extractors do parsing, and the
 * matcher decides what gets suggested. This class just unions the column sets
 * model-by-model and asks the matcher about each one.
 */
final class Scanner
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @return array{
     *   models: list<array{
     *     class: string,
     *     table: string,
     *     file: string,
     *     columns: list<string>,
     *     suggestions: list<array{
     *       column: string,
     *       result: array{vo: string, flags: array<string,string>}|array{candidate: true, note: string},
     *     }>,
     *     wrapped: list<string>,
     *   }>,
     *   stats: array{
     *     modelCount: int,
     *     columnCount: int,
     *     suggestionCount: int,
     *     candidateCount: int,
     *     wrappedCount: int,
     *   },
     * }
     */
    public function scan(string $modelsPath, ?string $migrationsPath = null): array
    {
        $models = $this->collectModels($modelsPath);
        $migrationTables = $migrationsPath !== null
            ? $this->collectMigrationTables($migrationsPath)
            : [];

        $report = [];
        $modelCount = 0;
        $columnCount = 0;
        $suggestionCount = 0;
        $candidateCount = 0;
        $wrappedCount = 0;

        foreach ($models as $model) {
            $tableName = $model['table'] ?? $this->guessTable($model['class']);
            $migrationColumns = $migrationTables[$tableName] ?? [];
            $allColumns = array_values(array_unique([...$model['columns'], ...$migrationColumns]));

            $suggestions = [];
            $wrapped = array_keys($model['wrappedCasts']);

            foreach ($allColumns as $column) {
                if (in_array($column, $wrapped, true)) {
                    continue;
                }

                $match = ColumnMatcher::match($column);

                if ($match === null) {
                    continue;
                }

                $suggestions[] = ['column' => $column, 'result' => $match];

                if (isset($match['candidate'])) {
                    $candidateCount++;
                } else {
                    $suggestionCount++;
                }
            }

            $report[] = [
                'class' => $model['class'],
                'table' => $tableName,
                'file' => $model['file'],
                'columns' => $allColumns,
                'suggestions' => $suggestions,
                'wrapped' => $wrapped,
            ];

            $modelCount++;
            $columnCount += count($allColumns);
            $wrappedCount += count($wrapped);
        }

        return [
            'models' => $report,
            'stats' => [
                'modelCount' => $modelCount,
                'columnCount' => $columnCount,
                'suggestionCount' => $suggestionCount,
                'candidateCount' => $candidateCount,
                'wrappedCount' => $wrappedCount,
            ],
        ];
    }

    /**
     * @return list<array{class: string, table: ?string, file: string, columns: list<string>, wrappedCasts: array<string,string>}>
     */
    private function collectModels(string $path): array
    {
        if (! $this->files->isDirectory($path)) {
            return [];
        }

        $models = [];

        foreach ($this->files->allFiles($path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = $this->files->get($file->getPathname());
            $extracted = ColumnExtractor::fromSource($source);

            if ($extracted['class'] === null) {
                continue;
            }

            $models[] = [
                'class' => $extracted['class'],
                'table' => $extracted['table'],
                'file' => $file->getPathname(),
                'columns' => $extracted['columns'],
                'wrappedCasts' => $extracted['wrappedCasts'],
            ];
        }

        return $models;
    }

    /**
     * @return array<string, list<string>>
     */
    private function collectMigrationTables(string $path): array
    {
        if (! $this->files->isDirectory($path)) {
            return [];
        }

        $tables = [];

        foreach ($this->files->allFiles($path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = $this->files->get($file->getPathname());

            foreach (MigrationExtractor::fromSource($source) as $tableName => $columns) {
                $tables[$tableName] = array_values(array_unique([
                    ...($tables[$tableName] ?? []),
                    ...$columns,
                ]));
            }
        }

        return $tables;
    }

    private function guessTable(string $className): string
    {
        return Str::snake(Str::pluralStudly($className));
    }
}
