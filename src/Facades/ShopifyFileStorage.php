<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage\Facades;

use FelixKerser\ShopifyFileStorage\ShopifyFileStorage as ShopifyFileStorageService;
use FelixKerser\ShopifyFileStorage\ShopifyFileStorageManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ShopifyFileStorageService store(?string $name = null)
 * @method static ShopifyFileStorageService connection(?string $name = null)
 * @method static ShopifyFileStorageService using(array<string, mixed> $config)
 * @method static void purge(?string $name = null)
 * @method static \FelixKerser\ShopifyFileStorage\PendingUpload upload(string|\Illuminate\Http\File|\Illuminate\Http\UploadedFile $file)
 * @method static list<\FelixKerser\ShopifyFileStorage\DTOs\ShopifyFile> getByIds(array<int, string> $gids)
 * @method static bool delete(string|array<array-key, string> $gids)
 *
 * @see ShopifyFileStorageManager
 */
class ShopifyFileStorage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ShopifyFileStorageManager::class;
    }
}
