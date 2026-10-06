<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage\DTOs;

use FelixKerser\ShopifyFileStorage\Exceptions\ShopifyGraphQLException;

final readonly class ShopifyStoreConfig
{
    public function __construct(
        public string $shopDomain,
        public string $clientId,
        public string $clientSecret,
        public string $apiVersion = '2026-01',
        public int $timeout = 30,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $shopDomain = self::requiredString($config, 'shop_domain', 'Shopify shop domain is not configured.');
        $clientId = self::requiredString($config, 'client_id', 'Shopify client id is not configured.');
        $clientSecret = self::requiredString($config, 'client_secret', 'Shopify client secret is not configured.');

        $apiVersion = trim((string) ($config['api_version'] ?? '2026-01'));

        if ($apiVersion === '') {
            throw new ShopifyGraphQLException('Shopify API version is not configured.');
        }

        $timeout = (int) ($config['timeout'] ?? 30);

        if ($timeout <= 0) {
            $timeout = 30;
        }

        return new self(
            shopDomain: self::normalizeHost($shopDomain),
            clientId: $clientId,
            clientSecret: $clientSecret,
            apiVersion: $apiVersion,
            timeout: $timeout,
        );
    }

    public function host(): string
    {
        return $this->shopDomain;
    }

    private static function normalizeHost(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = rtrim($domain, '/');

        if ($domain === '' || str_contains($domain, '/')) {
            throw new ShopifyGraphQLException('Shopify shop domain is not configured.');
        }

        return $domain;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function requiredString(array $config, string $key, string $message): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new ShopifyGraphQLException($message);
        }

        return $value;
    }
}
