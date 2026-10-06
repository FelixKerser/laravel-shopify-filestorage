<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage\Tests;

use FelixKerser\ShopifyFileStorage\Facades\ShopifyFileStorage;
use FelixKerser\ShopifyFileStorage\Services\ShopifyOAuthClient;
use FelixKerser\ShopifyFileStorage\ShopifyFileStorageServiceProvider;
use Illuminate\Support\Facades\Cache;
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
        config()->set('shopify-filestorage.default', 'default');
        config()->set('shopify-filestorage.shop_domain', 'demo-shop.myshopify.com');
        config()->set('shopify-filestorage.client_id', 'test-client-id');
        config()->set('shopify-filestorage.client_secret', 'test-client-secret');
        config()->set('shopify-filestorage.api_version', '2026-01');
        config()->set('shopify-filestorage.timeout', 30);
        config()->set('shopify-filestorage.stores', [
            'default' => [
                'shop_domain' => 'demo-shop.myshopify.com',
                'client_id' => 'test-client-id',
                'client_secret' => 'test-client-secret',
            ],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cache::put(
            ShopifyOAuthClient::cacheKey('demo-shop.myshopify.com', 'test-client-id', 'test-client-secret'),
            'oauth_test_token',
            3600,
        );
    }
}
