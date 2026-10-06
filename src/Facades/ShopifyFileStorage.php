<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \FelixKerser\ShopifyFileStorage\PendingUpload upload(string|\Illuminate\Http\File|\Illuminate\Http\UploadedFile $file)
 * @method static list<\FelixKerser\ShopifyFileStorage\DTOs\ShopifyFile> getByIds(array $gids)
 * @method static bool delete(string|array $gids)
 *
 * @see \FelixKerser\ShopifyFileStorage\ShopifyFileStorage
 */
class ShopifyFileStorage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \FelixKerser\ShopifyFileStorage\ShopifyFileStorage::class;
    }
}
