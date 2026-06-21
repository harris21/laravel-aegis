<?php

declare(strict_types=1);

use HarrisRafto\Aegis\Console\Scanner\ModulePaths;

function aegisModulesWorkspace(array $modules): string
{
    $root = sys_get_temp_dir().'/aegis-modules-'.uniqid();

    foreach ($modules as $name) {
        mkdir($root.'/'.$name.'/app/Models', 0777, true);
        mkdir($root.'/'.$name.'/database/migrations', 0777, true);
    }

    return $root;
}

it('discovers a models and migrations pair for every module on disk', function () {
    $root = aegisModulesWorkspace(['Blog', 'Shop']);
    config()->set('modules.paths.modules', $root);

    $pairs = ModulePaths::discover();

    expect($pairs)->toHaveCount(2);
    expect($pairs[0])->toBe([
        'models' => $root.'/Blog/app/Models',
        'migrations' => $root.'/Blog/database/migrations',
    ]);
    expect($pairs[1]['models'])->toBe($root.'/Shop/app/Models');

    aegisRemoveDir($root);
});

it('returns an empty list when laravel-modules config is absent', function () {
    config()->set('modules', null);

    expect(ModulePaths::discover())->toBe([]);
});

it('returns an empty list when the configured modules path does not exist', function () {
    config()->set('modules.paths.modules', sys_get_temp_dir().'/aegis-missing-'.uniqid());

    expect(ModulePaths::discover())->toBe([]);
});

it('honors configured generator subpaths for models and migrations', function () {
    $root = sys_get_temp_dir().'/aegis-modules-'.uniqid();
    mkdir($root.'/Blog/Entities', 0777, true);
    mkdir($root.'/Blog/Database/Migrations', 0777, true);

    config()->set('modules.paths.modules', $root);
    config()->set('modules.paths.generator.model.path', 'Entities');
    config()->set('modules.paths.generator.migration.path', 'Database/Migrations');

    $pairs = ModulePaths::discover();

    expect($pairs[0])->toBe([
        'models' => $root.'/Blog/Entities',
        'migrations' => $root.'/Blog/Database/Migrations',
    ]);

    aegisRemoveDir($root);
});

it('tolerates a plain-string generator path value', function () {
    $root = aegisModulesWorkspace(['Blog']);
    config()->set('modules.paths.modules', $root);
    config()->set('modules.paths.generator.model', 'app/Models');

    expect(ModulePaths::discover()[0]['models'])->toBe($root.'/Blog/app/Models');

    aegisRemoveDir($root);
});

it('falls back to app/Models and database/migrations when generator config is absent', function () {
    $root = aegisModulesWorkspace(['Blog']);
    config()->set('modules.paths.modules', $root);

    expect(ModulePaths::discover()[0])->toBe([
        'models' => $root.'/Blog/app/Models',
        'migrations' => $root.'/Blog/database/migrations',
    ]);

    aegisRemoveDir($root);
});
