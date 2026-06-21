<?php

declare(strict_types=1);

function aegisModuleWithModel(string $column): string
{
    $root = sys_get_temp_dir().'/aegis-cmd-'.uniqid();
    mkdir($root.'/Blog/app/Models', 0777, true);
    mkdir($root.'/Blog/database/migrations', 0777, true);

    file_put_contents(
        $root.'/Blog/app/Models/Post.php',
        '<?php namespace Modules\Blog\Models; class Post extends Model { protected $fillable = ["'.$column.'"]; }'
    );

    return $root;
}

it('folds auto-detected module directories into the default scan', function () {
    $root = aegisModuleWithModel('author_email');
    config()->set('modules.paths.modules', $root);

    $this->artisan('vo:scan')
        ->expectsOutputToContain('author_email')
        ->assertSuccessful();

    aegisRemoveDir($root);
});

it('skips module directories when --no-modules is passed', function () {
    $root = aegisModuleWithModel('author_email');
    config()->set('modules.paths.modules', $root);

    $this->artisan('vo:scan --no-modules')
        ->doesntExpectOutputToContain('author_email')
        ->assertSuccessful();

    aegisRemoveDir($root);
});

it('does not auto-detect modules when an explicit --path is given', function () {
    $root = aegisModuleWithModel('author_email');
    config()->set('modules.paths.modules', $root);

    $empty = sys_get_temp_dir().'/aegis-empty-'.uniqid();
    mkdir($empty, 0777, true);

    $this->artisan('vo:scan', ['--path' => $empty])
        ->doesntExpectOutputToContain('author_email')
        ->assertSuccessful();

    aegisRemoveDir($root);
    rmdir($empty);
});

it('does not auto-detect modules when --path is explicitly set to the default value', function () {
    $root = aegisModuleWithModel('author_email');
    config()->set('modules.paths.modules', $root);

    $this->artisan('vo:scan', ['--path' => 'app/Models'])
        ->doesntExpectOutputToContain('author_email')
        ->assertSuccessful();

    aegisRemoveDir($root);
});
