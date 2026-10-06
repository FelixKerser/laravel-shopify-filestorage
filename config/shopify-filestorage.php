<?php

declare(strict_types=1);

return [

    /*
     * Named store used by ShopifyFileStorage::upload(), getByIds(), and delete()
     * when no store is selected explicitly.
     */
    'default' => env('SHOPIFY_FILESTORAGE_STORE', 'default'),

    /*
     * Legacy flat credentials for the default store. Prefer stores.default in
     * new applications. These keys still override stores.default at runtime.
     */
    'shop_domain' => env('SHOPIFY_SHOP_DOMAIN'),

    'client_id' => env('SHOPIFY_CLIENT_ID'),

    'client_secret' => env('SHOPIFY_CLIENT_SECRET'),

    /*
     * Admin GraphQL version used in /admin/api/{version}/graphql.json.
     */
    'api_version' => env('SHOPIFY_API_VERSION', '2026-01'),

    /*
     * Seconds for the OAuth token request, the GraphQL call, and the staged binary upload.
     */
    'timeout' => (int) env('SHOPIFY_HTTP_TIMEOUT', 30),

    /*
     * One or more Shopify stores. Each store needs a shop_domain. client_id and
     * client_secret inherit from the default store when omitted.
     */
    'stores' => [
        'default' => [
            'shop_domain' => env('SHOPIFY_SHOP_DOMAIN'),
            'client_id' => env('SHOPIFY_CLIENT_ID'),
            'client_secret' => env('SHOPIFY_CLIENT_SECRET'),
        ],
    ],

];
