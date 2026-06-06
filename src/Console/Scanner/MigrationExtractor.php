<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis\Console\Scanner;

/**
 * Pulls table → columns mappings out of migration files.
 *
 * Targets the standard shape: `Schema::create('table', function (Blueprint $table) { ... })`
 * with column declarations like `$table->string('email')` inside the closure.
 *
 * Ignores Schema::table('...') alter calls, anonymous-class migrations, and
 * dynamic table names. The 80% case covers the vast majority of column
 * discoveries we'd miss from model files alone.
 */
final class MigrationExtractor
{
    private const COLUMN_METHODS = [
        'bigInteger', 'binary', 'boolean', 'char', 'date', 'dateTime', 'dateTimeTz',
        'decimal', 'double', 'enum', 'float', 'foreignId', 'foreignUlid', 'foreignUuid',
        'geography', 'geometry', 'integer', 'ipAddress', 'json', 'jsonb', 'longText',
        'macAddress', 'mediumInteger', 'mediumText', 'morphs', 'nullableMorphs',
        'nullableUlidMorphs', 'nullableUuidMorphs', 'set', 'smallInteger', 'string',
        'text', 'time', 'timeTz', 'timestamp', 'timestampTz', 'tinyInteger', 'tinyText',
        'ulid', 'uuid', 'year',
    ];

    /**
     * @return array<string, list<string>> table name => unique column names
     */
    public static function fromSource(string $source): array
    {
        $tables = [];
        $methodList = implode('|', self::COLUMN_METHODS);

        $createPattern = '/Schema::create\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*function\s*\([^)]*\)\s*(?:use\s*\([^)]*\)\s*)?\{/';

        if (preg_match_all($createPattern, $source, $matches, PREG_OFFSET_CAPTURE) !== false) {
            foreach ($matches[0] as $i => $headerMatch) {
                $tableName = $matches[1][$i][0];
                $bodyStart = $headerMatch[1] + strlen($headerMatch[0]);
                $body = self::extractClosureBody($source, $bodyStart);

                if ($body === null) {
                    continue;
                }

                $tables[$tableName] = self::columnsFromClosureBody($body, $methodList);
            }
        }

        return $tables;
    }

    private static function extractClosureBody(string $source, int $start): ?string
    {
        $depth = 1;
        $i = $start;
        $length = strlen($source);

        while ($i < $length && $depth > 0) {
            $char = $source[$i];

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $start, $i - $start);
                }
            }

            $i++;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function columnsFromClosureBody(string $body, string $methodList): array
    {
        $columns = [];

        // $table->columnMethod('name', ...)
        $pattern = '/\$table\s*->\s*(?:'.$methodList.')\s*\(\s*[\'"]([^\'"]+)[\'"]/';

        if (preg_match_all($pattern, $body, $matches)) {
            foreach ($matches[1] as $name) {
                $columns[] = $name;
            }
        }

        return array_values(array_unique($columns));
    }
}
