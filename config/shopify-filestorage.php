<?php

declare(strict_types=1);

return [

    /*
     * Admin shop host, with or without a scheme.
     * Example: your-shop.myshopify.com
     */
    'shop_domain' => env('SHOPIFY_SHOP_DOMAIN'),

    /*
     * Admin API access token. The app needs read_files and write_files.
     */
    'access_token' => env('SHOPIFY_ADMIN_ACCESS_TOKEN'),

    /*
     * Admin GraphQL version used in /admin/api/{version}/graphql.json.
     */
    'api_version' => env('SHOPIFY_API_VERSION', '2026-01'),

    /*
     * Seconds for both the GraphQL call and the staged binary upload.
     */
    'timeout' => (int) env('SHOPIFY_HTTP_TIMEOUT', 30),

];
