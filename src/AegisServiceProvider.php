<?php

declare(strict_types=1);

namespace HarrisRafto\Aegis;

use HarrisRafto\Aegis\Console\MakeValueObjectCommand;
use Illuminate\Support\ServiceProvider;

final class AegisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/aegis.php', 'aegis');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/aegis.php' => config_path('aegis.php'),
            ], 'aegis-config');

            $this->commands([
                MakeValueObjectCommand::class,
            ]);
        }
    }
}
