<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage;

use FelixKerser\ShopifyFileStorage\Actions\UploadFileAction;
use FelixKerser\ShopifyFileStorage\Services\ShopifyCdnClient;
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
        $this->app->singleton(ShopifyCdnClient::class);
        $this->app->singleton(UploadFileAction::class);
        $this->app->singleton(ShopifyFileStorage::class);
    }
}
