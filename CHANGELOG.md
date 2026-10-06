# Changelog

All notable changes to `felixkerser/laravel-shopify-filestorage` are documented here.

## Unreleased

- Support Laravel 12 and 13, alongside Laravel 10 and 11.
- Authenticate with the OAuth 2.0 client credentials grant. Configure `SHOPIFY_CLIENT_ID` and `SHOPIFY_CLIENT_SECRET` instead of a static Admin API access token.
- Ship Laravel Boost guidelines at `resources/boost/guidelines/core.blade.php` and a `shopify-filestorage` skill.

## 1.0.0 - 2026-10-05

- Staged Shopify file uploads through `stagedUploadsCreate`, a multipart binary POST, and `fileCreate`.
- `ShopifyFileStorage::getByIds()` and `ShopifyFileStorage::delete()` for Admin file ids.
- `ShopifyUrl` helpers for CDN resize, crop, and format transforms.
- Domain exceptions for GraphQL failures, upload failures, and invalid CDN urls.
