<?php

declare(strict_types=1);

use FelixKerser\ShopifyFileStorage\Exceptions\ShopifyGraphQLException;
use FelixKerser\ShopifyFileStorage\Exceptions\ShopifyUploadException;
use FelixKerser\ShopifyFileStorage\Facades\ShopifyFileStorage;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

const SHOPIFY_GRAPHQL = 'https://demo-shop.myshopify.com/admin/api/2026-01/graphql.json';
const STAGED_UPLOAD_URL = 'https://shopify-staged-uploads.storage.googleapis.com/upload';
const STAGED_RESOURCE_URL = 'https://shopify-staged-uploads.storage.googleapis.com/tmp/banner.jpg';

beforeEach(function () {
    Http::preventStrayRequests();
});

test('upload runs stagedUploadsCreate, posts the binary, then fileCreate', function () {
    Http::fake(shopifyUploadFake());

    $path = shopifyFixture('banner.jpg', shopifyJpegBytes());

    $file = ShopifyFileStorage::upload($path)
        ->asImage()
        ->withAlt('Banner')
        ->save();

    expect($file->id)->toBe('gid://shopify/MediaImage/1')
        ->and($file->url)->toBe('https://cdn.shopify.com/s/files/1/0001/0002/files/banner.jpg')
        ->and($file->alt)->toBe('Banner')
        ->and($file->mediaType)->toBe('IMAGE')
        ->and($file->status)->toBe('READY');

    $recorded = Http::recorded();
    expect($recorded)->toHaveCount(3);

    /** @var Request $stagedRequest */
    $stagedRequest = $recorded[0][0];
    /** @var Request $binaryRequest */
    $binaryRequest = $recorded[1][0];
    /** @var Request $createRequest */
    $createRequest = $recorded[2][0];

    expect($stagedRequest->url())->toBe(SHOPIFY_GRAPHQL)
        ->and($stagedRequest->hasHeader('X-Shopify-Access-Token', 'oauth_test_token'))->toBeTrue()
        ->and($stagedRequest->data()['variables']['input'][0])->toMatchArray([
            'filename' => 'banner.jpg',
            'mimeType' => 'image/jpeg',
            'resource' => 'IMAGE',
            'httpMethod' => 'POST',
            'fileSize' => (string) strlen(shopifyJpegBytes()),
        ]);

    expect($binaryRequest->url())->toBe(STAGED_UPLOAD_URL)
        ->and($binaryRequest->hasHeader('X-Shopify-Access-Token'))->toBeFalse()
        ->and($binaryRequest->body())->toContain('name="key"')
        ->and($binaryRequest->body())->toContain('tmp/banner.jpg')
        ->and($binaryRequest->body())->toContain('name="policy"')
        ->and($binaryRequest->body())->toContain('policy-token')
        ->and($binaryRequest->body())->toContain('name="file"')
        ->and($binaryRequest->body())->toContain('filename="banner.jpg"')
        ->and($binaryRequest->body())->toContain('Content-Type: image/jpeg');

    expect($createRequest->url())->toBe(SHOPIFY_GRAPHQL)
        ->and($createRequest->data()['variables']['files'][0])->toMatchArray([
            'originalSource' => STAGED_RESOURCE_URL,
            'contentType' => 'IMAGE',
            'filename' => 'banner.jpg',
            'alt' => 'Banner',
        ])
        ->and($createRequest->body())->toContain('fileCreate');
});

test('upload accepts an uploaded file and can force a filename and video resource', function () {
    Http::fake(shopifyUploadFake(mediaType: 'Video', id: 'gid://shopify/Video/9'));

    $path = shopifyFixture('source.jpg', shopifyJpegBytes());
    $upload = new UploadedFile($path, 'source.jpg', 'image/jpeg', null, true);

    $file = ShopifyFileStorage::upload($upload)
        ->asVideo()
        ->withFilename('clip.mp4')
        ->withAlt('Clip')
        ->save();

    expect($file->id)->toBe('gid://shopify/Video/9')
        ->and($file->mediaType)->toBe('VIDEO');

    Http::assertSent(fn (Request $request): bool => $request->url() === SHOPIFY_GRAPHQL
        && str_contains($request->body(), 'stagedUploadsCreate')
        && ($request->data()['variables']['input'][0]['filename'] ?? null) === 'clip.mp4'
        && ($request->data()['variables']['input'][0]['resource'] ?? null) === 'VIDEO');
});

test('upload infers a generic file from a text payload', function () {
    Http::fake(shopifyUploadFake(mediaType: 'GenericFile', id: 'gid://shopify/GenericFile/4'));

    $path = shopifyFixture('price-list.txt', 'sku,qty');

    $file = ShopifyFileStorage::upload($path)->asFile()->save();

    expect($file->mediaType)->toBe('FILE')
        ->and($file->url)->toBe('https://cdn.shopify.com/s/files/1/0001/0002/files/price-list.txt');

    Http::assertSent(fn (Request $request): bool => str_contains($request->body(), 'stagedUploadsCreate')
        && ($request->data()['variables']['input'][0]['resource'] ?? null) === 'FILE'
        && ($request->data()['variables']['input'][0]['mimeType'] ?? null) === 'text/plain'
        && ($request->data()['variables']['input'][0]['filename'] ?? null) === 'price-list.txt');
});

test('upload infers an image when no resource is selected', function () {
    Http::fake(shopifyUploadFake());

    ShopifyFileStorage::upload(shopifyFixture('plain.jpg', shopifyJpegBytes()))->save();

    Http::assertSent(fn (Request $request): bool => str_contains($request->body(), 'stagedUploadsCreate')
        && ($request->data()['variables']['input'][0]['resource'] ?? null) === 'IMAGE');
});

test('a shop domain with a scheme is normalized into the graphql endpoint', function () {
    config()->set('shopify-filestorage.shop_domain', 'https://Demo-Shop.myshopify.com/');
    config()->set('shopify-filestorage.api_version', '2026-04');

    Http::fake(shopifyUploadFake());

    ShopifyFileStorage::upload(shopifyFixture('banner.jpg', shopifyJpegBytes()))->asImage()->save();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://demo-shop.myshopify.com/admin/api/2026-04/graphql.json'
        && str_contains($request->body(), 'stagedUploadsCreate'));
});

test('staged upload user errors throw ShopifyUploadException and skip the binary upload', function () {
    Http::fake([
        'demo-shop.myshopify.com/*' => Http::response([
            'data' => [
                'stagedUploadsCreate' => [
                    'stagedTargets' => [],
                    'userErrors' => [
                        ['field' => ['filename'], 'message' => 'Filename is invalid.'],
                    ],
                ],
            ],
        ]),
    ]);

    expect(fn () => ShopifyFileStorage::upload(shopifyFixture('banner.jpg', shopifyJpegBytes()))->asImage()->save())
        ->toThrow(ShopifyUploadException::class, 'Filename is invalid.');

    Http::assertNotSent(fn (Request $request): bool => $request->url() === STAGED_UPLOAD_URL);
});

test('a failed binary upload throws ShopifyUploadException after the staged target was created', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->body(), 'stagedUploadsCreate')) {
            return Http::response(stagedTargetResponse());
        }

        return Http::response('signature mismatch', 403);
    });

    try {
        ShopifyFileStorage::upload(shopifyFixture('banner.jpg', shopifyJpegBytes()))->asImage()->save();
        throw new RuntimeException('The upload should have failed.');
    } catch (ShopifyUploadException $exception) {
        expect($exception->step)->toBe('binaryUpload')
            ->and($exception->getMessage())->toContain('signature mismatch');
    }
});

test('fileCreate user errors throw ShopifyUploadException', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->body(), 'stagedUploadsCreate')) {
            return Http::response(stagedTargetResponse());
        }

        if ($request->url() === STAGED_UPLOAD_URL) {
            return Http::response('', 201);
        }

        return Http::response([
            'data' => [
                'fileCreate' => [
                    'files' => [],
                    'userErrors' => [
                        ['field' => ['files'], 'message' => 'Original source is invalid.'],
                    ],
                ],
            ],
        ]);
    });

    expect(fn () => ShopifyFileStorage::upload(shopifyFixture('banner.jpg', shopifyJpegBytes()))->asImage()->save())
        ->toThrow(ShopifyUploadException::class, 'Original source is invalid.');
});

test('graphql errors stay ShopifyGraphQLException', function () {
    Http::fake([
        'demo-shop.myshopify.com/*' => Http::response([
            'errors' => [
                ['message' => 'Throttled'],
            ],
        ], 200),
    ]);

    expect(fn () => ShopifyFileStorage::upload(shopifyFixture('banner.jpg', shopifyJpegBytes()))->asImage()->save())
        ->toThrow(ShopifyGraphQLException::class, 'Throttled');
});

test('an unauthorized admin response exposes the http status', function () {
    Http::fake([
        'demo-shop.myshopify.com/*' => Http::response(['errors' => 'nope'], 401),
    ]);

    try {
        ShopifyFileStorage::upload(shopifyFixture('banner.jpg', shopifyJpegBytes()))->asImage()->save();
        throw new RuntimeException('The request should have failed.');
    } catch (ShopifyGraphQLException $exception) {
        expect($exception->statusCode)->toBe(401)
            ->and($exception->getMessage())->toContain('401');
    }
});

test('a missing local file throws before any http call', function () {
    Http::fake();

    expect(fn () => ShopifyFileStorage::upload('/tmp/does-not-exist-shopify.jpg')->asImage()->save())
        ->toThrow(ShopifyUploadException::class);

    Http::assertNothingSent();
});

test('an empty shop domain or client credentials are rejected', function () {
    config()->set('shopify-filestorage.shop_domain', '   ');
    config()->set('shopify-filestorage.stores.default.shop_domain', '   ');

    expect(fn () => ShopifyFileStorage::upload(shopifyFixture('banner.jpg', shopifyJpegBytes()))->asImage()->save())
        ->toThrow(ShopifyGraphQLException::class, 'shop domain');

    ShopifyFileStorage::purge();
    config()->set('shopify-filestorage.shop_domain', 'demo-shop.myshopify.com');
    config()->set('shopify-filestorage.stores.default.shop_domain', 'demo-shop.myshopify.com');
    config()->set('shopify-filestorage.client_id', '');
    config()->set('shopify-filestorage.stores.default.client_id', '');

    expect(fn () => ShopifyFileStorage::upload(shopifyFixture('banner.jpg', shopifyJpegBytes()))->asImage()->save())
        ->toThrow(ShopifyGraphQLException::class, 'client id');

    ShopifyFileStorage::purge();
    config()->set('shopify-filestorage.client_id', 'test-client-id');
    config()->set('shopify-filestorage.stores.default.client_id', 'test-client-id');
    config()->set('shopify-filestorage.client_secret', ' ');
    config()->set('shopify-filestorage.stores.default.client_secret', ' ');

    expect(fn () => ShopifyFileStorage::upload(shopifyFixture('banner.jpg', shopifyJpegBytes()))->asImage()->save())
        ->toThrow(ShopifyGraphQLException::class, 'client secret');
});

/**
 * @return Closure(Request): Response
 */
function shopifyUploadFake(string $mediaType = 'MediaImage', string $id = 'gid://shopify/MediaImage/1'): Closure
{
    return function (Request $request) use ($mediaType, $id) {
        if (str_contains($request->body(), 'stagedUploadsCreate')) {
            return Http::response(stagedTargetResponse());
        }

        if ($request->url() === STAGED_UPLOAD_URL) {
            return Http::response('', 204);
        }

        $filename = $request->data()['variables']['files'][0]['filename'] ?? 'banner.jpg';

        return Http::response([
            'data' => [
                'fileCreate' => [
                    'files' => [createdFileNode($mediaType, $id, is_string($filename) ? $filename : 'banner.jpg')],
                    'userErrors' => [],
                ],
            ],
        ]);
    };
}

/**
 * @return array<string, mixed>
 */
function stagedTargetResponse(): array
{
    return [
        'data' => [
            'stagedUploadsCreate' => [
                'stagedTargets' => [[
                    'url' => STAGED_UPLOAD_URL,
                    'resourceUrl' => STAGED_RESOURCE_URL,
                    'parameters' => [
                        ['name' => 'key', 'value' => 'tmp/banner.jpg'],
                        ['name' => 'Content-Type', 'value' => 'image/jpeg'],
                        ['name' => 'policy', 'value' => 'policy-token'],
                    ],
                ]],
                'userErrors' => [],
            ],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function createdFileNode(string $mediaType, string $id, string $filename): array
{
    $node = [
        '__typename' => $mediaType,
        'id' => $id,
        'alt' => 'Banner',
        'fileStatus' => 'READY',
    ];

    $url = 'https://cdn.shopify.com/s/files/1/0001/0002/files/'.$filename;

    return match ($mediaType) {
        'Video' => $node + [
            'originalSource' => ['url' => $url],
            'sources' => [],
        ],
        'GenericFile' => $node + ['url' => $url],
        default => $node + ['image' => ['url' => $url]],
    };
}
