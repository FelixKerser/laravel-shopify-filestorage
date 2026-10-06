<?php

declare(strict_types=1);

use FelixKerser\ShopifyFileStorage\Exceptions\ShopifyGraphQLException;
use FelixKerser\ShopifyFileStorage\Facades\ShopifyFileStorage;
use FelixKerser\ShopifyFileStorage\Services\ShopifyOAuthClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const SHOPIFY_TOKEN_URL = 'https://demo-shop.myshopify.com/admin/oauth/access_token';

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
});

test('client credentials are exchanged once and reused for graphql', function () {
    Http::fake(function (Request $request) {
        if ($request->url() === SHOPIFY_TOKEN_URL) {
            return Http::response([
                'access_token' => 'shpca_exchanged',
                'scope' => 'read_files,write_files',
                'expires_in' => 86399,
            ]);
        }

        return Http::response([
            'data' => [
                'nodes' => [],
            ],
        ]);
    });

    expect(ShopifyFileStorage::getByIds(['gid://shopify/MediaImage/1']))->toBe([])
        ->and(ShopifyFileStorage::getByIds(['gid://shopify/MediaImage/2']))->toBe([]);

    $tokenRequests = Http::recorded(
        fn (Request $request): bool => $request->url() === SHOPIFY_TOKEN_URL,
    );

    expect($tokenRequests)->toHaveCount(1);

    /** @var Request $tokenRequest */
    $tokenRequest = $tokenRequests[0][0];

    expect($tokenRequest->isForm())->toBeTrue()
        ->and($tokenRequest->data())->toMatchArray([
            'grant_type' => 'client_credentials',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ])
        ->and($tokenRequest->hasHeader('X-Shopify-Access-Token'))->toBeFalse();

    Http::assertSent(fn (Request $request): bool => $request->url() === SHOPIFY_GRAPHQL
        && $request->hasHeader('X-Shopify-Access-Token', 'shpca_exchanged')
        && ! str_contains($request->body(), 'test-client-secret'));

    expect(Cache::get(ShopifyOAuthClient::cacheKey(
        'demo-shop.myshopify.com',
        'test-client-id',
        'test-client-secret',
    )))->toBe('shpca_exchanged');
});

test('a failed token request does not call the admin api', function () {
    Http::fake([
        SHOPIFY_TOKEN_URL => Http::response(['error' => 'invalid_client'], 401),
    ]);

    try {
        ShopifyFileStorage::getByIds(['gid://shopify/MediaImage/1']);
        throw new RuntimeException('The token request should have failed.');
    } catch (ShopifyGraphQLException $exception) {
        expect($exception->statusCode)->toBe(401)
            ->and($exception->getMessage())->toBe('Shopify OAuth token request failed with status 401. invalid_client');
    }

    Http::assertNotSent(fn (Request $request): bool => $request->url() === SHOPIFY_GRAPHQL);
});

test('a token response without an access token is rejected', function () {
    Http::fake([
        SHOPIFY_TOKEN_URL => Http::response([
            'scope' => 'read_files',
            'expires_in' => 86399,
        ]),
    ]);

    expect(fn () => ShopifyFileStorage::getByIds(['gid://shopify/MediaImage/1']))
        ->toThrow(ShopifyGraphQLException::class, 'did not include an access token');
});

test('changing the client secret requests a new token', function () {
    Cache::put(
        ShopifyOAuthClient::cacheKey('demo-shop.myshopify.com', 'test-client-id', 'test-client-secret'),
        'oauth_test_token',
        3600,
    );

    config()->set('shopify-filestorage.client_secret', 'rotated-secret');

    Http::fake(function (Request $request) {
        if ($request->url() === SHOPIFY_TOKEN_URL) {
            return Http::response([
                'access_token' => 'shpca_rotated',
                'scope' => 'read_files,write_files',
                'expires_in' => 120,
            ]);
        }

        return Http::response(['data' => ['nodes' => []]]);
    });

    ShopifyFileStorage::getByIds(['gid://shopify/MediaImage/1']);

    Http::assertSent(fn (Request $request): bool => $request->url() === SHOPIFY_TOKEN_URL
        && ($request->data()['client_secret'] ?? null) === 'rotated-secret');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Shopify-Access-Token', 'shpca_rotated'));
});
