<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage\Support;

use FelixKerser\ShopifyFileStorage\DTOs\ShopifyStoreConfig;
use InvalidArgumentException;

final class ShopifyStoreConfigResolver
{
    public static function defaultStoreName(): string
    {
        $name = config('shopify-filestorage.default');

        if (! is_string($name) || trim($name) === '') {
            return 'default';
        }

        return trim($name);
    }

    public static function resolve(string $name): ShopifyStoreConfig
    {
        $root = config('shopify-filestorage');

        if (! is_array($root)) {
            throw new InvalidArgumentException('Shopify file storage is not configured.');
        }

        $stores = self::normalizedStores($root);

        if (! array_key_exists($name, $stores)) {
            throw new InvalidArgumentException("Shopify file storage store [{$name}] is not configured.");
        }

        $defaultName = self::defaultStoreName();
        $default = self::defaultStoreConfig($root, $stores, $defaultName);

        $store = $stores[$name];

        if ($name !== $defaultName) {
            $store = array_merge($default, $store);
        } else {
            $store = $default;
        }

        return ShopifyStoreConfig::fromArray($store);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): ShopifyStoreConfig
    {
        $root = config('shopify-filestorage');
        $root = is_array($root) ? $root : [];

        $merged = array_merge([
            'api_version' => $root['api_version'] ?? '2026-01',
            'timeout' => $root['timeout'] ?? 30,
        ], $config);

        return ShopifyStoreConfig::fromArray($merged);
    }

    /**
     * @param  array<string, mixed>  $root
     * @return array<string, array<string, mixed>>
     */
    private static function normalizedStores(array $root): array
    {
        $stores = $root['stores'] ?? null;

        if (! is_array($stores) || $stores === []) {
            return [
                'default' => self::legacyOverrides($root),
            ];
        }

        /** @var array<string, array<string, mixed>> $stores */
        return $stores;
    }

    /**
     * @param  array<string, mixed>  $root
     * @param  array<string, array<string, mixed>>  $stores
     * @return array<string, mixed>
     */
    private static function defaultStoreConfig(array $root, array $stores, string $defaultName): array
    {
        $store = is_array($stores[$defaultName] ?? null) ? $stores[$defaultName] : [];
        $store = array_merge($store, self::legacyOverrides($root));
        $store['api_version'] = $store['api_version'] ?? $root['api_version'] ?? '2026-01';
        $store['timeout'] = $store['timeout'] ?? $root['timeout'] ?? 30;

        return $store;
    }

    /**
     * @param  array<string, mixed>  $root
     * @return array<string, mixed>
     */
    private static function legacyOverrides(array $root): array
    {
        $overrides = [];

        foreach (['shop_domain', 'client_id', 'client_secret'] as $key) {
            $value = $root[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $overrides[$key] = trim($value);
            }
        }

        return $overrides;
    }
}
