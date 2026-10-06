<p align="center">
  <strong>Laravel Shopify File Storage</strong>
</p>

<p align="center">
  Admin API file uploads and CDN image transforms for Laravel.
</p>

<p align="center">
  <a href="https://github.com/FelixKerser/laravel-shopify-filestorage/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/FelixKerser/laravel-shopify-filestorage/run-tests.yml?branch=main&label=tests" alt="Tests"></a>
  <a href="https://packagist.org/packages/felixkerser/laravel-shopify-filestorage"><img src="https://img.shields.io/packagist/v/felixkerser/laravel-shopify-filestorage.svg" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/felixkerser/laravel-shopify-filestorage"><img src="https://img.shields.io/packagist/php-v/felixkerser/laravel-shopify-filestorage.svg" alt="PHP Version"></a>
  <a href="https://packagist.org/packages/felixkerser/laravel-shopify-filestorage"><img src="https://img.shields.io/badge/laravel-10%20%E2%80%93%2013-FF2D20" alt="Laravel 10 through 13"></a>
  <a href="LICENSE"><img src="https://img.shields.io/packagist/l/felixkerser/laravel-shopify-filestorage.svg" alt="License"></a>
</p>

`felixkerser/laravel-shopify-filestorage` uploads a local file through Shopify's Admin GraphQL file pipeline and rewrites Shopify CDN image URLs. The upload client performs the HTTP calls. `ShopifyUrl` does not.

The Admin app needs the `read_files` and `write_files` scopes.

## Requirements

- PHP 8.2 or newer. Laravel 13 needs PHP 8.3
- Laravel 10.x, 11.x, 12.x, or 13.x
- A Dev Dashboard app client id and client secret for the OAuth 2.0 client credentials grant

The package is PSR-12, tested with Pest, and analysed with Larastan at level 8.

## Installation

Install the package with Composer:

```bash
composer require felixkerser/laravel-shopify-filestorage
```

Laravel discovers the service provider and the `ShopifyFileStorage` facade. Publish the config file when the host application should own the credentials:

```bash
php artisan vendor:publish --tag=shopify-filestorage-config
```

This copies `config/shopify-filestorage.php` into the application.

## Configuration

Set the shop host and the Dev Dashboard app credentials in `.env`:

```dotenv
SHOPIFY_SHOP_DOMAIN=your-shop.myshopify.com
SHOPIFY_CLIENT_ID=your-client-id
SHOPIFY_CLIENT_SECRET=your-client-secret
SHOPIFY_API_VERSION=2026-01
SHOPIFY_HTTP_TIMEOUT=30
```

`shop_domain` accepts a bare host or a host with a scheme. Before the first Admin call, the client posts the OAuth 2.0 client credentials grant:

```text
POST https://{shop_domain}/admin/oauth/access_token
grant_type=client_credentials
client_id={client_id}
client_secret={client_secret}
```

Shopify returns `access_token`, `scope`, and `expires_in` (`86399` seconds). The package stores the token in the application cache and requests a new one 60 seconds before it expires. A changed client id or client secret uses a new cache entry. GraphQL calls then go to:

```text
https://{shop_domain}/admin/api/{api_version}/graphql.json
```

Every GraphQL request sends that access token in `X-Shopify-Access-Token`. The client secret is not sent to GraphQL. `api_version` defaults to `2026-01`. `timeout` defaults to `30` seconds and covers the token request, the GraphQL call, and the staged binary upload.

The app and the store must belong to the same Shopify organization, and the app must be installed on the store. The client credentials grant is the Dev Dashboard replacement for a static Admin API access token.

The published file defines a default store, optional legacy flat credentials, and a `stores` map. Flat `SHOPIFY_*` keys still override `stores.default` at runtime.

## Multiple stores

Name each Shopify shop under `stores`. `client_id` and `client_secret` inherit from the default store when they are omitted on another store. Set `SHOPIFY_FILESTORAGE_STORE` to change which store `ShopifyFileStorage::upload()` uses without an explicit store call.

```dotenv
SHOPIFY_FILESTORAGE_STORE=default
SHOPIFY_SHOP_DOMAIN=your-shop.myshopify.com
SHOPIFY_CLIENT_ID=your-client-id
SHOPIFY_CLIENT_SECRET=your-client-secret
SHOPIFY_EU_SHOP_DOMAIN=eu-shop.myshopify.com
```

```php
'stores' => [
    'default' => [
        'shop_domain' => env('SHOPIFY_SHOP_DOMAIN'),
        'client_id' => env('SHOPIFY_CLIENT_ID'),
        'client_secret' => env('SHOPIFY_CLIENT_SECRET'),
    ],
    'eu' => [
        'shop_domain' => env('SHOPIFY_EU_SHOP_DOMAIN'),
    ],
],
```

Pick a store before upload, fetch, or delete. Admin file GIDs belong to one shop, so keep the store name beside each GID in the application database.

```php
ShopifyFileStorage::store('eu')->upload($path)->asImage()->save();
ShopifyFileStorage::connection('eu')->getByIds([$gid]);
```

For a one-off shop configuration, `using()` builds a client without adding it to `stores`:

```php
ShopifyFileStorage::using([
    'shop_domain' => 'tenant.myshopify.com',
    'client_id' => '...',
    'client_secret' => '...',
])->upload($file)->save();
```

After changing store credentials in config at runtime, call `ShopifyFileStorage::purge()` so cached connections are rebuilt.

## Upload a file

`ShopifyFileStorage::upload()` accepts an absolute path or an `Illuminate\Http\File` (`UploadedFile` included). `save()` runs the three Admin steps:

1. `stagedUploadsCreate` with `filename`, `mimeType`, `fileSize`, `httpMethod: POST`, and `resource` `IMAGE`, `VIDEO`, or `FILE`.
2. Multipart POST to `stagedTargets.url`. Each returned parameter is a form field. The binary is the last field and is named `file`.
3. `fileCreate` with `originalSource` set to the staged `resourceUrl`, plus `contentType`, `filename`, and `alt` when alt text was provided.

```php
use FelixKerser\ShopifyFileStorage\Facades\ShopifyFileStorage;
use FelixKerser\ShopifyFileStorage\Support\ShopifyUrl;

public function store(Request $request): JsonResponse
{
    $file = ShopifyFileStorage::upload($request->file('banner'))
        ->asImage()
        ->withAlt('Banner')
        ->save();

    return response()->json([
        'id' => $file->id,
        'url' => $file->url,
        'alt' => $file->alt,
        'media_type' => $file->mediaType,
        'status' => $file->status,
    ]);
}
```

`asImage()`, `asVideo()`, and `asFile()` set the Shopify resource. Without one of those calls, the resource is inferred from the MIME type: `image/*` becomes `IMAGE`, `video/*` becomes `VIDEO`, and every other type becomes `FILE`.

`withFilename()` replaces the name sent to Shopify. `withAlt()` sets the file alt text. A newly created file often comes back with `status` `PROCESSING` and `url` `null` until Shopify finishes it.

`ShopifyFile` is a read-only object with `id`, `url`, `alt`, `mediaType`, and `status`.

A path upload uses the same chain:

```php
$file = ShopifyFileStorage::upload(storage_path('app/catalog.pdf'))
    ->asFile()
    ->withFilename('catalog.pdf')
    ->save();
```

## Retrieve files

`getByIds()` loads Admin GIDs through the `nodes` query. The result follows the request order. Unknown ids are omitted. An empty list does not call Shopify. More than 250 ids is rejected.

```php
$files = ShopifyFileStorage::getByIds([
    'gid://shopify/MediaImage/1',
    'gid://shopify/GenericFile/2',
    'gid://shopify/Video/3',
]);
```

`mediaType` is `IMAGE`, `VIDEO`, `FILE`, or `MODEL_3D`.

## Delete files

`delete()` accepts one GID or a list and runs `fileDelete`. Duplicate ids are sent once. An empty list returns `true` and does not call Shopify. The call returns `true` when every requested id is present in `deletedFileIds`.

```php
ShopifyFileStorage::delete('gid://shopify/MediaImage/1');

ShopifyFileStorage::delete([
    'gid://shopify/MediaImage/1',
    'gid://shopify/GenericFile/2',
]);
```

## CDN URLs

`ShopifyUrl` rewrites a Shopify CDN image URL on the host. It accepts `cdn.shopify.com`, `*.myshopify.com`, and other `*.shopify.com` / Shopify CDN hosts, including protocol-relative URLs.

`resize()` writes the current `image_url` query parameters: `width`, `height`, and `crop`. Existing `width`, `height`, and `crop` parameters are replaced. Every other parameter, including `v`, stays in place. A legacy filename size (`_800x600`, `_100x`, `_x100`, `_large`, `_800x600_crop_center`) is removed so the original asset name is restored before the new query is applied.

A crop requires both dimensions. Supported crops are `top`, `center`, `bottom`, `left`, and `right`. Each dimension is an integer from 1 to 5760.

```php
use FelixKerser\ShopifyFileStorage\Support\ShopifyUrl;

$source = 'https://cdn.shopify.com/s/files/1/0001/0002/files/banner.jpg?v=1710000000';

ShopifyUrl::resize($source, 1600, 900, 'center');
// https://cdn.shopify.com/s/files/1/0001/0002/files/banner.jpg?v=1710000000&width=1600&height=900&crop=center

ShopifyUrl::resize(
    'https://cdn.shopify.com/s/files/1/0001/0002/files/banner_300x200_crop_center.jpg?v=1710000000',
    800,
    null,
);
// https://cdn.shopify.com/s/files/1/0001/0002/files/banner.jpg?v=1710000000&width=800
```

`format()` supports `webp`, `avif`, `png`, and `jpg`. `webp` and `avif` keep the source extension and append the transform extension (`banner.jpg` becomes `banner.jpg.webp`). `png` and `jpg` replace the source extension. A second call with the same format does not stack another extension. The `format` query parameter is set to the same value, and the `v` / size parameters stay.

```php
ShopifyUrl::format($source, 'webp');
// https://cdn.shopify.com/s/files/1/0001/0002/files/banner.jpg.webp?v=1710000000&format=webp

ShopifyUrl::format(ShopifyUrl::resize($source, 800, 800, 'center'), 'webp');
// https://cdn.shopify.com/s/files/1/0001/0002/files/banner.jpg.webp?v=1710000000&width=800&height=800&crop=center&format=webp
```

| Source | Call | Result |
| --- | --- | --- |
| `banner.jpg?v=1&width=10` | `resize(..., 800, 600)` | `banner.jpg?v=1&width=800&height=600` |
| `banner_100x100.png?v=1` | `format(..., 'webp')` | `banner_100x100.png.webp?v=1&format=webp` |
| `banner.jpg.webp?v=1` | `format(..., 'webp')` | `banner.jpg.webp?v=1&format=webp` |
| `banner.jpg.webp?v=1` | `format(..., 'jpg')` | `banner.jpg?v=1&format=jpg` |

## Exceptions

| Exception | When it is thrown |
| --- | --- |
| `ShopifyUploadException` | The local file cannot be read, `stagedUploadsCreate` or `fileCreate` returns `userErrors`, or the staged storage target rejects the multipart POST. `step` is `read`, `stagedUploadsCreate`, `binaryUpload`, or `fileCreate`. |
| `ShopifyGraphQLException` | The OAuth token request fails, the Admin HTTP call fails, GraphQL returns a top-level `errors` payload, or `getByIds` / `delete` cannot be completed. `statusCode` is set for an HTTP failure. |
| `InvalidCdnUrlException` | `ShopifyUrl` receives a URL that is not a Shopify CDN image. |
| `InvalidArgumentException` | A resize dimension, crop, or format is outside the supported set. |

## Laravel Boost

The package ships a Boost guideline and a skill. `php artisan boost:install` in the host application loads them from the installed package. This package does not depend on `laravel/boost`.

- `resources/boost/guidelines/core.blade.php` is included in the agent guidelines.
- `resources/boost/skills/shopify-filestorage/SKILL.md` is installed when skills are enabled.

## Testing

```bash
composer test
composer analyse
composer format
```

`composer test` runs the Pest suite. Upload tests fake the OAuth token endpoint, the Admin GraphQL endpoint, and the staged storage URL with `Http::fake()`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE](LICENSE) for the full text.
