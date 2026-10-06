---
name: shopify-filestorage
description: Upload, fetch, and delete Shopify files and rewrite Shopify CDN image URLs with felixkerser/laravel-shopify-filestorage. Use when a Laravel app stores media through the Shopify Admin file pipeline or builds Shopify CDN image transforms.
---

# Shopify file storage

Use `FelixKerser\ShopifyFileStorage\Facades\ShopifyFileStorage` for Admin file calls and `FelixKerser\ShopifyFileStorage\Support\ShopifyUrl` for CDN URLs. Do not add a filesystem disk for this package.

## Credentials

```dotenv
SHOPIFY_SHOP_DOMAIN=your-shop.myshopify.com
SHOPIFY_CLIENT_ID=your-client-id
SHOPIFY_CLIENT_SECRET=your-client-secret
SHOPIFY_API_VERSION=2026-01
SHOPIFY_HTTP_TIMEOUT=30
```

Publish the config with `php artisan vendor:publish --tag=shopify-filestorage-config`.

The package exchanges `client_id` and `client_secret` at `POST https://{shop}/admin/oauth/access_token` with `grant_type=client_credentials`. It caches `access_token` and refreshes it 60 seconds before `expires_in`. The app and store must be in the same Shopify organization. Scopes: `read_files`, `write_files`.

## Upload

1. `stagedUploadsCreate` with `filename`, `mimeType`, `resource` (`IMAGE`, `VIDEO`, or `FILE`), `fileSize` as a string, and `httpMethod` `POST`.
2. Multipart POST to the staged `url`. Shopify parameters are form fields. The binary field is named `file` and is last.
3. `fileCreate` with `originalSource` set to `resourceUrl`, plus `contentType`, `filename`, and `alt` when alt text was set.

```php
$file = ShopifyFileStorage::upload($request->file('banner'))
    ->asImage()
    ->withAlt('Banner')
    ->withFilename('banner.jpg')
    ->save();
```

`upload()` accepts an absolute path, `Illuminate\Http\File`, or `UploadedFile`. For an uploaded file, the default filename is `getClientOriginalName()`. Without `asImage()`, `asVideo()`, or `asFile()`, infer the resource from the MIME type.

A new file often returns `status` `PROCESSING` and `url` `null`. Store `$file->id` and resolve the URL with `getByIds()`.

## Retrieve and delete

```php
$files = ShopifyFileStorage::getByIds([
    'gid://shopify/MediaImage/1',
    'gid://shopify/GenericFile/2',
]);

ShopifyFileStorage::delete([
    'gid://shopify/MediaImage/1',
    'gid://shopify/GenericFile/2',
]);
```

`getByIds()` follows request order and omits null nodes. `mediaType` is `IMAGE`, `VIDEO`, `FILE`, or `MODEL_3D`. `delete()` returns `true` when every requested id is present in `deletedFileIds`.

## CDN URLs

```php
use FelixKerser\ShopifyFileStorage\Support\ShopifyUrl;

ShopifyUrl::resize($url, 1600, 900, 'center');
ShopifyUrl::format($url, 'webp');
```

Crops are `top`, `center`, `bottom`, `left`, and `right`. Each dimension is an integer from 1 to 5760. These helpers reject hosts that are not Shopify CDN image hosts.

## Failures

- `ShopifyUploadException`: local read, staged or `fileCreate` `userErrors`, or the binary POST. Use `step`.
- `ShopifyGraphQLException`: token request, Admin HTTP status, or top-level GraphQL `errors`. Use `statusCode`.
- `InvalidCdnUrlException`: `ShopifyUrl` received a non-Shopify image URL.
- `InvalidArgumentException`: resize dimension, crop, or format is outside the supported set.
