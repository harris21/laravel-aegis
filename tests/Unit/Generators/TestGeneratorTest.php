<?php

declare(strict_types=1);

use HarrisRafto\Aegis\Console\Generators\TestGenerator;

it('generates a Pest stub by default', function () {
    $source = (new TestGenerator('Email', 'App\\Domain\\ValueObjects'))->generate();

    expect($source)
        ->toContain('use App\\Domain\\ValueObjects\\Email;')
        ->toContain("it('Email')->todo();")
        ->not->toContain('class EmailTest')
        ->not->toContain('PHPUnit\\Framework\\TestCase');
});

it('generates a PHPUnit stub when usePest is false', function () {
    $source = (new TestGenerator('Email', 'App\\Domain\\ValueObjects', usePest: false))->generate();

    expect($source)
        ->toContain('namespace Tests\\Unit;')
        ->toContain('use App\\Domain\\ValueObjects\\Email;')
        ->toContain('use PHPUnit\\Framework\\TestCase;')
        ->toContain('class EmailTest extends TestCase')
        ->toContain('public function testItIsPending(): void')
        ->toContain('$this->markTestIncomplete(')
        ->not->toContain("it('");
});

it('passes the Value Object class name through both stubs', function () {
    $pest = (new TestGenerator('OrderStatus', 'App\\Domain\\ValueObjects'))->generate();
    $phpunit = (new TestGenerator('OrderStatus', 'App\\Domain\\ValueObjects', usePest: false))->generate();

    expect($pest)->toContain("it('OrderStatus')->todo();");
    expect($phpunit)->toContain('class OrderStatusTest extends TestCase');
});
