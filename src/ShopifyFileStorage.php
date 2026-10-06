<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage;

use FelixKerser\ShopifyFileStorage\Actions\UploadFileAction;
use FelixKerser\ShopifyFileStorage\DTOs\ShopifyFile;
use FelixKerser\ShopifyFileStorage\Services\ShopifyCdnClient;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;

class ShopifyFileStorage
{
    public function __construct(
        private readonly ShopifyCdnClient $client,
        private readonly UploadFileAction $uploads,
    ) {}

    public function upload(string|File|UploadedFile $file): PendingUpload
    {
        return new PendingUpload($this->uploads, $file);
    }

    /**
     * @param  list<string>  $gids
     * @return list<ShopifyFile>
     */
    public function getByIds(array $gids): array
    {
        return $this->client->filesByIds($gids);
    }

    /**
     * @param  string|array<array-key, string>  $gids
     */
    public function delete(string|array $gids): bool
    {
        $ids = is_string($gids) ? [$gids] : array_values($gids);

        return $this->client->deleteFiles($ids);
    }
}
