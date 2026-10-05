<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage\Tests;

use FelixKerser\ShopifyFileStorage\Facades\ShopifyFileStorage;
use FelixKerser\ShopifyFileStorage\ShopifyFileStorageServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ShopifyFileStorageServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'ShopifyFileStorage' => ShopifyFileStorage::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('shopify-filestorage.shop_domain', 'demo-shop.myshopify.com');
        config()->set('shopify-filestorage.access_token', 'shpat_test_token');
        config()->set('shopify-filestorage.api_version', '2026-01');
        config()->set('shopify-filestorage.timeout', 30);
    }
}
