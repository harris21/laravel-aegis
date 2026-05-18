<?php

declare(strict_types=1);

use HarrisRafto\Aegis\AegisServiceProvider;

it('boots the Aegis service provider', function () {
    $providers = $this->app->getLoadedProviders();

    expect($providers)->toHaveKey(AegisServiceProvider::class);
});

it('merges the aegis config', function () {
    expect(config('aegis.namespace'))->toBe('App\\Domain\\ValueObjects');
    expect(config('aegis.path'))->toBeNull();
});
