<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

function aegisFreshWorkspace(): string
{
    $workspace = sys_get_temp_dir().'/aegis-test-'.uniqid();
    File::ensureDirectoryExists($workspace);
    config()->set('aegis.path', $workspace);

    return $workspace;
}

it('scaffolds a Value Object at the configured path', function () {
    $workspace = aegisFreshWorkspace();

    $this->artisan('make:value-object', [
        'name' => 'Email',
        '--rule' => 'email',
        '--normalize' => 'lower',
        '--no-test' => true,
    ])->assertExitCode(0);

    $expected = $workspace.'/Email.php';
    expect(File::exists($expected))->toBeTrue();

    $contents = File::get($expected);
    expect($contents)
        ->toContain('final readonly class Email')
        ->toContain('filter_var($value, FILTER_VALIDATE_EMAIL)')
        ->toContain('$value = mb_strtolower($value);')
        ->toContain('ComparesCastableAttributes');

    File::deleteDirectory($workspace);
});

it('rejects names that are not PascalCase', function () {
    $workspace = aegisFreshWorkspace();

    $this->artisan('make:value-object', [
        'name' => 'emailAddress',
        '--no-test' => true,
    ])->assertExitCode(1);

    File::deleteDirectory($workspace);
});

it('rejects unknown validation rules', function () {
    $workspace = aegisFreshWorkspace();

    $this->artisan('make:value-object', [
        'name' => 'Email',
        '--rule' => 'nonsense',
        '--no-test' => true,
    ])->assertExitCode(1);

    File::deleteDirectory($workspace);
});

it('rejects malformed --method specs', function () {
    $workspace = aegisFreshWorkspace();

    $this->artisan('make:value-object', [
        'name' => 'Email',
        '--method' => ['1invalid'],
        '--no-test' => true,
    ])->assertExitCode(1);

    File::deleteDirectory($workspace);
});

it('rejects --cast without a dot separator', function () {
    $workspace = aegisFreshWorkspace();

    $this->artisan('make:value-object', [
        'name' => 'Email',
        '--cast' => 'OrderEmail',
        '--no-test' => true,
    ])->assertExitCode(1);

    File::deleteDirectory($workspace);
});

it('runs in dry-run mode without writing anything', function () {
    $workspace = aegisFreshWorkspace();

    $this->artisan('make:value-object', [
        'name' => 'Email',
        '--rule' => 'email',
        '--no-test' => true,
        '--dry-run' => true,
    ])->assertExitCode(0);

    expect(File::exists($workspace.'/Email.php'))->toBeFalse();

    File::deleteDirectory($workspace);
});

it('emits method stubs with the requested return type', function () {
    $workspace = aegisFreshWorkspace();

    $this->artisan('make:value-object', [
        'name' => 'Email',
        '--rule' => 'email',
        '--method' => ['domain:string', 'isCorporate:bool'],
        '--no-test' => true,
    ])->assertExitCode(0);

    $contents = File::get($workspace.'/Email.php');

    expect($contents)
        ->toContain('public function domain(): string')
        ->toContain('public function isCorporate(): bool');

    File::deleteDirectory($workspace);
});
