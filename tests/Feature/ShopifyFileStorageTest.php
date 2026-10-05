<?php

declare(strict_types=1);

use FelixKerser\ShopifyFileStorage\Exceptions\ShopifyGraphQLException;
use FelixKerser\ShopifyFileStorage\Facades\ShopifyFileStorage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('getByIds returns files in request order and skips missing nodes', function () {
    Http::fake([
        'demo-shop.myshopify.com/*' => Http::response([
            'data' => [
                'nodes' => [
                    [
                        '__typename' => 'MediaImage',
                        'id' => 'gid://shopify/MediaImage/1',
                        'alt' => 'One',
                        'fileStatus' => 'PROCESSING',
                        'image' => null,
                    ],
                    null,
                    [
                        '__typename' => 'GenericFile',
                        'id' => 'gid://shopify/GenericFile/2',
                        'alt' => null,
                        'fileStatus' => 'READY',
                        'url' => 'https://cdn.shopify.com/s/files/1/1/files/spec.pdf',
                    ],
                    [
                        '__typename' => 'Video',
                        'id' => 'gid://shopify/Video/3',
                        'alt' => 'Clip',
                        'fileStatus' => 'READY',
                        'originalSource' => ['url' => null],
                        'sources' => [
                            ['url' => 'https://cdn.shopify.com/videos/clip.mp4'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $files = ShopifyFileStorage::getByIds([
        'gid://shopify/MediaImage/1',
        'gid://shopify/Missing/0',
        'gid://shopify/GenericFile/2',
        'gid://shopify/Video/3',
    ]);

    expect($files)->toHaveCount(3)
        ->and($files[0]->id)->toBe('gid://shopify/MediaImage/1')
        ->and($files[0]->url)->toBeNull()
        ->and($files[0]->status)->toBe('PROCESSING')
        ->and($files[0]->mediaType)->toBe('IMAGE')
        ->and($files[1]->mediaType)->toBe('FILE')
        ->and($files[1]->url)->toBe('https://cdn.shopify.com/s/files/1/1/files/spec.pdf')
        ->and($files[2]->mediaType)->toBe('VIDEO')
        ->and($files[2]->url)->toBe('https://cdn.shopify.com/videos/clip.mp4');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Shopify-Access-Token', 'shpat_test_token')
        && $request->data()['variables']['ids'] === [
            'gid://shopify/MediaImage/1',
            'gid://shopify/Missing/0',
            'gid://shopify/GenericFile/2',
            'gid://shopify/Video/3',
        ]);
});

test('getByIds with an empty list does not call shopify', function () {
    Http::fake();

    expect(ShopifyFileStorage::getByIds([]))->toBe([]);

    Http::assertNothingSent();
});

test('delete removes one id or many ids', function () {
    Http::fake([
        'demo-shop.myshopify.com/*' => Http::response([
            'data' => [
                'fileDelete' => [
                    'deletedFileIds' => [
                        'gid://shopify/MediaImage/1',
                        'gid://shopify/GenericFile/2',
                    ],
                    'userErrors' => [],
                ],
            ],
        ]),
    ]);

    expect(ShopifyFileStorage::delete('gid://shopify/MediaImage/1'))->toBeTrue()
        ->and(ShopifyFileStorage::delete([
            'gid://shopify/MediaImage/1',
            'gid://shopify/GenericFile/2',
            'gid://shopify/MediaImage/1',
        ]))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->data()['variables']['fileIds'] === [
        'gid://shopify/MediaImage/1',
        'gid://shopify/GenericFile/2',
    ]);
});

test('delete with an empty list does not call shopify', function () {
    Http::fake();

    expect(ShopifyFileStorage::delete([]))->toBeTrue();

    Http::assertNothingSent();
});

test('delete throws when shopify reports a user error', function () {
    Http::fake([
        'demo-shop.myshopify.com/*' => Http::response([
            'data' => [
                'fileDelete' => [
                    'deletedFileIds' => [],
                    'userErrors' => [
                        ['message' => 'File does not exist.'],
                    ],
                ],
            ],
        ]),
    ]);

    expect(fn () => ShopifyFileStorage::delete('gid://shopify/MediaImage/404'))
        ->toThrow(ShopifyGraphQLException::class, 'File does not exist.');
});

test('delete throws when shopify omits a requested id', function () {
    Http::fake([
        'demo-shop.myshopify.com/*' => Http::response([
            'data' => [
                'fileDelete' => [
                    'deletedFileIds' => [],
                    'userErrors' => [],
                ],
            ],
        ]),
    ]);

    expect(fn () => ShopifyFileStorage::delete(['gid://shopify/MediaImage/1']))
        ->toThrow(ShopifyGraphQLException::class, 'did not delete');
});

test('blank file ids are rejected', function () {
    expect(fn () => ShopifyFileStorage::getByIds(['']))
        ->toThrow(ShopifyGraphQLException::class, 'non-empty');
});

test('published config keeps the documented defaults', function () {
    $config = require dirname(__DIR__, 2).'/config/shopify-filestorage.php';

    expect($config)->toHaveKeys(['shop_domain', 'access_token', 'api_version', 'timeout'])
        ->and($config['api_version'])->toBe('2026-01')
        ->and($config['timeout'])->toBe(30);
});
