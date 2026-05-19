<?php

declare(strict_types=1);

use HarrisRafto\Aegis\Console\Scanner\ColumnMatcher;

it('matches email columns', function () {
    $result = ColumnMatcher::match('email');
    expect($result)->toMatchArray(['vo' => 'Email', 'flags' => ['rule' => 'email', 'normalize' => 'lower']]);

    expect(ColumnMatcher::match('billing_email'))->not->toBeNull();
    expect(ColumnMatcher::match('contact_email')['vo'])->toBe('Email');
});

it('matches url columns', function () {
    expect(ColumnMatcher::match('website_url')['vo'])->toBe('Url');
    expect(ColumnMatcher::match('avatar_link')['vo'])->toBe('Url');
    expect(ColumnMatcher::match('url')['flags'])->toBe(['rule' => 'url', 'normalize' => 'trim']);
});

it('matches uuid columns', function () {
    expect(ColumnMatcher::match('uuid')['vo'])->toBe('Uuid');
    expect(ColumnMatcher::match('external_uuid')['vo'])->toBe('Uuid');
});

it('matches ip address columns', function () {
    expect(ColumnMatcher::match('ip')['vo'])->toBe('IpAddress');
    expect(ColumnMatcher::match('ip_address')['vo'])->toBe('IpAddress');
    expect(ColumnMatcher::match('last_login_ip')['vo'])->toBe('IpAddress');
    expect(ColumnMatcher::match('remote_ip_address')['vo'])->toBe('IpAddress');
});

it('matches slug columns', function () {
    expect(ColumnMatcher::match('slug')['vo'])->toBe('Slug');
    expect(ColumnMatcher::match('article_slug')['vo'])->toBe('Slug');
});

it('matches country_code and currency_code exactly', function () {
    expect(ColumnMatcher::match('country_code')['vo'])->toBe('CountryCode');
    expect(ColumnMatcher::match('currency_code')['vo'])->toBe('CurrencyCode');
});

it('returns a money candidate for cents columns without a runnable command', function () {
    $result = ColumnMatcher::match('total_amount_cents');

    expect($result)->toHaveKey('candidate');
    expect($result)->not->toHaveKey('vo');
    expect($result['note'])->toContain('Money');
});

it('returns a status candidate when no enum is known', function () {
    $result = ColumnMatcher::match('order_status');

    expect($result)->toHaveKey('candidate');
    expect($result['note'])->toContain('--type=App\\Enums');
});

it('returns null for unrecognised columns', function () {
    expect(ColumnMatcher::match('name'))->toBeNull();
    expect(ColumnMatcher::match('password'))->toBeNull();
    expect(ColumnMatcher::match('created_at'))->toBeNull();
});

it('is case-insensitive on the column name', function () {
    expect(ColumnMatcher::match('EMAIL')['vo'])->toBe('Email');
    expect(ColumnMatcher::match('Country_Code')['vo'])->toBe('CountryCode');
});
