<?php

declare(strict_types=1);

use HarrisRafto\Aegis\Console\Scanner\MigrationExtractor;

it('extracts table and columns from a standard Schema::create migration', function () {
    $source = <<<'PHP'
<?php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('name');
            $table->timestamps();
        });
    }
};
PHP;

    $result = MigrationExtractor::fromSource($source);

    expect($result)->toHaveKey('users');
    expect($result['users'])->toContain('email')->toContain('name');
});

it('handles multiple column methods in one migration', function () {
    $source = <<<'PHP'
<?php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->string('email');
    $table->char('country_code', 2);
    $table->char('currency_code', 3);
    $table->bigInteger('total_amount_cents');
    $table->json('metadata');
    $table->timestamps();
});
PHP;

    $columns = MigrationExtractor::fromSource($source)['orders'];

    expect($columns)->toContain('email')
        ->toContain('country_code')
        ->toContain('currency_code')
        ->toContain('total_amount_cents')
        ->toContain('metadata');
});

it('handles multiple Schema::create calls in one migration', function () {
    $source = <<<'PHP'
<?php
Schema::create('users', function (Blueprint $table) {
    $table->string('email');
});

Schema::create('profiles', function (Blueprint $table) {
    $table->string('bio_url');
});
PHP;

    $result = MigrationExtractor::fromSource($source);

    expect($result)->toHaveKeys(['users', 'profiles']);
    expect($result['users'])->toContain('email');
    expect($result['profiles'])->toContain('bio_url');
});

it('ignores Schema::table alter calls for now', function () {
    $source = <<<'PHP'
<?php
Schema::table('users', function (Blueprint $table) {
    $table->string('phone');
});
PHP;

    expect(MigrationExtractor::fromSource($source))->toBe([]);
});

it('returns an empty array for migrations with no Schema::create call', function () {
    $source = '<?php // empty migration';
    expect(MigrationExtractor::fromSource($source))->toBe([]);
});
