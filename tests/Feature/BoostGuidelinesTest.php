<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('the boost guideline renders the shopify file storage contract', function () {
    $path = dirname(__DIR__, 2).'/resources/boost/guidelines/core.blade.php';
    $rendered = Blade::render(file_get_contents($path));

    expect($rendered)->toContain('ShopifyFileStorage::upload')
        ->and($rendered)->toContain('SHOPIFY_CLIENT_ID')
        ->and($rendered)->toContain('client credentials')
        ->and($rendered)->toContain('X-Shopify-Access-Token')
        ->and($rendered)->toContain('ShopifyUrl::resize')
        ->and($rendered)->toContain('does not register a Laravel filesystem disk');
});

test('the boost skill names itself and describes shopify file uploads', function () {
    $skill = file_get_contents(dirname(__DIR__, 2).'/resources/boost/skills/shopify-filestorage/SKILL.md');

    expect($skill)->toContain('name: shopify-filestorage')
        ->and($skill)->toContain('felixkerser/laravel-shopify-filestorage')
        ->and($skill)->toContain('grant_type=client_credentials')
        ->and($skill)->toContain('stagedUploadsCreate');
});
