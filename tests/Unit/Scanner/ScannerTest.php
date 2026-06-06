<?php

declare(strict_types=1);

use HarrisRafto\Aegis\Console\Scanner\Scanner;
use Illuminate\Filesystem\Filesystem;

function aegisScannerWorkspace(): string
{
    $root = sys_get_temp_dir().'/aegis-scanner-'.uniqid();
    mkdir($root.'/models', 0777, true);
    mkdir($root.'/migrations', 0777, true);

    return $root;
}

it('scans a single model and reports candidate columns', function () {
    $root = aegisScannerWorkspace();

    file_put_contents($root.'/models/User.php', <<<'PHP'
<?php
class User extends Model
{
    protected $fillable = ['name', 'email', 'country_code'];
}
PHP);

    $report = (new Scanner(new Filesystem))->scan($root.'/models', $root.'/migrations');

    expect($report['stats']['modelCount'])->toBe(1);
    expect($report['stats']['suggestionCount'])->toBe(2); // email + country_code
    expect($report['models'][0]['class'])->toBe('User');

    array_map('unlink', glob($root.'/models/*.php'));
    rmdir($root.'/models');
    rmdir($root.'/migrations');
    rmdir($root);
});

it('unions model columns with migration columns by table name', function () {
    $root = aegisScannerWorkspace();

    file_put_contents($root.'/models/Order.php', <<<'PHP'
<?php
class Order extends Model
{
    protected $table = 'orders';
    protected $fillable = ['email'];
}
PHP);

    file_put_contents($root.'/migrations/2026_01_01_create_orders.php', <<<'PHP'
<?php
Schema::create('orders', function (Blueprint $table) {
    $table->string('email');
    $table->char('country_code', 2);
    $table->bigInteger('total_amount_cents');
});
PHP);

    $report = (new Scanner(new Filesystem))->scan($root.'/models', $root.'/migrations');

    $orderColumns = $report['models'][0]['columns'];
    expect($orderColumns)->toContain('email')
        ->toContain('country_code')
        ->toContain('total_amount_cents');

    expect($report['stats']['suggestionCount'])->toBe(2);    // email + country_code
    expect($report['stats']['candidateCount'])->toBe(1);     // total_amount_cents (Money)

    array_map('unlink', glob($root.'/models/*.php'));
    array_map('unlink', glob($root.'/migrations/*.php'));
    rmdir($root.'/models');
    rmdir($root.'/migrations');
    rmdir($root);
});

it('counts wrapped columns and reports coverage', function () {
    $root = aegisScannerWorkspace();

    file_put_contents($root.'/models/User.php', <<<'PHP'
<?php
class User extends Model
{
    protected $fillable = ['name', 'email'];

    protected function casts(): array
    {
        return [
            'email' => \App\Domain\ValueObjects\Email::class,
        ];
    }
}
PHP);

    $report = (new Scanner(new Filesystem))->scan($root.'/models', null);

    expect($report['stats']['wrappedCount'])->toBe(1);
    expect($report['models'][0]['wrapped'])->toContain('email');
    // Suggestions should NOT include 'email' since it's already wrapped
    foreach ($report['models'][0]['suggestions'] as $s) {
        expect($s['column'])->not->toBe('email');
    }

    array_map('unlink', glob($root.'/models/*.php'));
    rmdir($root.'/models');
    rmdir($root.'/migrations');
    rmdir($root);
});

it('derives the table name from the class name when $table is absent', function () {
    $root = aegisScannerWorkspace();

    file_put_contents($root.'/models/OrderItem.php', '<?php class OrderItem extends Model {}');
    file_put_contents($root.'/migrations/create_order_items.php', <<<'PHP'
<?php
Schema::create('order_items', function (Blueprint $table) {
    $table->string('product_email');
});
PHP);

    $report = (new Scanner(new Filesystem))->scan($root.'/models', $root.'/migrations');

    expect($report['models'][0]['table'])->toBe('order_items');
    expect($report['models'][0]['columns'])->toContain('product_email');

    array_map('unlink', glob($root.'/models/*.php'));
    array_map('unlink', glob($root.'/migrations/*.php'));
    rmdir($root.'/models');
    rmdir($root.'/migrations');
    rmdir($root);
});
