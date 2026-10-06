<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage;

use FelixKerser\ShopifyFileStorage\Actions\UploadFileAction;
use FelixKerser\ShopifyFileStorage\DTOs\ShopifyStoreConfig;
use FelixKerser\ShopifyFileStorage\Services\ShopifyCdnClient;
use FelixKerser\ShopifyFileStorage\Services\ShopifyOAuthClient;
use FelixKerser\ShopifyFileStorage\Support\ShopifyStoreConfigResolver;

class ShopifyFileStorageManager
{
    /** @var array<string, ShopifyFileStorage> */
    private array $connections = [];

    public function store(?string $name = null): ShopifyFileStorage
    {
        return $this->connection($name);
    }

    public function connection(?string $name = null): ShopifyFileStorage
    {
        $name ??= ShopifyStoreConfigResolver::defaultStoreName();

        return $this->connections[$name] ??= $this->makeConnection(
            ShopifyStoreConfigResolver::resolve($name),
        );
    }

    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->connections = [];

            return;
        }

        unset($this->connections[$name]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function using(array $config): ShopifyFileStorage
    {
        return $this->makeConnection(ShopifyStoreConfigResolver::fromArray($config));
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->{$method}(...$parameters);
    }

    private function makeConnection(ShopifyStoreConfig $config): ShopifyFileStorage
    {
        $oauth = new ShopifyOAuthClient($config);
        $client = new ShopifyCdnClient($oauth, $config);
        $uploads = new UploadFileAction($client);

        return new ShopifyFileStorage($client, $uploads);
    }
}
