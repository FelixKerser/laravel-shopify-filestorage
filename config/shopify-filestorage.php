<?php

declare(strict_types=1);

return [

    /*
     * Admin shop host, with or without a scheme.
     * Example: your-shop.myshopify.com
     */
    'shop_domain' => env('SHOPIFY_SHOP_DOMAIN'),

    /*
     * Dev Dashboard app credentials. The client credentials grant exchanges
     * these for an access token. The app needs read_files and write_files.
     */
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

];
