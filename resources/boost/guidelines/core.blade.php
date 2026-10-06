## Laravel Shopify File Storage

`felixkerser/laravel-shopify-filestorage` uploads files with the Shopify Admin GraphQL pipeline and rewrites Shopify CDN image URLs on the host. Call the `ShopifyFileStorage` facade and `ShopifyUrl`. Shopify files are GIDs and often stay `PROCESSING` with a null URL, so this package does not register a Laravel filesystem disk.

### Authentication

Publish `shopify-filestorage-config` when the app should own the credentials. Configure `SHOPIFY_SHOP_DOMAIN`, `SHOPIFY_CLIENT_ID`, and `SHOPIFY_CLIENT_SECRET`, or add named entries under `stores`. Use `ShopifyFileStorage::store('name')` when Admin calls target a shop other than the default. Keep the store name with each Shopify file GID in the database. The client posts the OAuth 2.0 client credentials grant to `https://{shop}/admin/oauth/access_token` and caches the access token until 60 seconds before `expires_in`. GraphQL requests send that token in `X-Shopify-Access-Token`. The app and the store must belong to the same Shopify organization, the app must be installed, and the scopes must include `read_files` and `write_files`.

### Uploads

`upload()` accepts an absolute path, `Illuminate\Http\File`, or `UploadedFile`. Chain `asImage()`, `asVideo()`, or `asFile()` before `save()`. Without one of those calls, `image/*` becomes `IMAGE`, `video/*` becomes `VIDEO`, and every other MIME type becomes `FILE`. `withAlt()` and `withFilename()` are optional. `save()` runs `stagedUploadsCreate`, posts the binary with the `file` field last, then `fileCreate`.

@verbatim
<code-snippet name="Upload a Shopify image" lang="php">
use FelixKerser\ShopifyFileStorage\Facades\ShopifyFileStorage;

$file = ShopifyFileStorage::upload($request->file('banner'))
    ->asImage()
    ->withAlt('Banner')
    ->save();
</code-snippet>
@endverbatim

Read `$file->id`, `$file->url`, `$file->alt`, `$file->mediaType`, and `$file->status`. Keep the GID and load the URL later with `getByIds()` when `status` is `PROCESSING`.

### Retrieve and delete

`getByIds()` preserves request order, skips unknown ids, rejects more than 250 ids, and does not call Shopify for an empty list. `delete()` accepts one GID or a list, sends duplicates once, and returns `true` only when every requested id is in `deletedFileIds`. An empty list returns `true` and does not call Shopify.

@verbatim
<code-snippet name="Load and delete Shopify files" lang="php">
$files = ShopifyFileStorage::getByIds(['gid://shopify/MediaImage/1']);

ShopifyFileStorage::delete('gid://shopify/MediaImage/1');
</code-snippet>
@endverbatim

### CDN URLs

`ShopifyUrl::resize($url, $width, $height, $crop)` and `ShopifyUrl::format($url, $format)` do not perform HTTP. Resize writes `width`, `height`, and `crop` and strips a legacy filename size first. A crop requires both dimensions. Formats are `webp`, `avif`, `png`, and `jpg`. `webp` and `avif` append a chained extension.

### Exceptions

Throw `ShopifyUploadException` for a local read failure, `userErrors` on `stagedUploadsCreate` or `fileCreate`, and a rejected binary POST. Read `step`: `read`, `stagedUploadsCreate`, `binaryUpload`, or `fileCreate`. Throw `ShopifyGraphQLException` for an OAuth or Admin HTTP failure and for top-level GraphQL `errors`. Read `statusCode` on HTTP failures. Throw `InvalidCdnUrlException` when the URL is not a Shopify CDN image.
