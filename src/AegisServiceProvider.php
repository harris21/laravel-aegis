<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis;

use HarrisRafto\Aegis\Console\MakeValueObjectCommand;
use HarrisRafto\Aegis\Console\ScanCommand;
use HarrisRafto\Aegis\Rules\ValueObjectRule;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rule;

final class AegisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/aegis.php', 'aegis');
    }

    public function boot(): void
    {
        Rule::macro('valueObject', static function (string $valueObjectClass): ValueObjectRule {
            return new ValueObjectRule($valueObjectClass);
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/aegis.php' => config_path('aegis.php'),
            ], ['aegis', 'aegis-config']);

            $this->publishes([
                __DIR__.'/Console/Stubs/value-object.stub' => base_path('stubs/aegis.value-object.stub'),
                __DIR__.'/Console/Stubs/value-object-test.stub' => base_path('stubs/aegis.value-object-test.stub'),
            ], ['aegis', 'aegis-stubs']);

            $this->commands([
                MakeValueObjectCommand::class,
                ScanCommand::class,
            ]);
        }
    }
}
