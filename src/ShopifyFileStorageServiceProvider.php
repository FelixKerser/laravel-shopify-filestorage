<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ShopifyFileStorageServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('shopify-filestorage')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ShopifyFileStorageManager::class);
    }
}
