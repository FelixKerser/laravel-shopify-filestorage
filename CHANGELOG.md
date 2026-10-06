# Changelog

All notable changes to `felixkerser/laravel-shopify-filestorage` are documented here.

## Unreleased

## 0.0.2 - 2026-10-06

- Named Shopify stores through `stores` in config, `ShopifyFileStorage::store()` / `connection()`, and one-off `using()` overrides.
- Legacy flat `SHOPIFY_SHOP_DOMAIN`, `SHOPIFY_CLIENT_ID`, and `SHOPIFY_CLIENT_SECRET` still configure the default store.
- CI installs Laravel matrix dependencies with Composer audit blocking disabled for framework advisories during package tests.

## 0.0.1 - 2026-10-06

- Staged Shopify file uploads through `stagedUploadsCreate`, a multipart binary POST, and `fileCreate`.
- `ShopifyFileStorage::getByIds()` and `ShopifyFileStorage::delete()` for Admin file ids.
- `ShopifyUrl` helpers for CDN resize, crop, and format transforms.
- Domain exceptions for GraphQL failures, upload failures, and invalid CDN urls.
- Support Laravel 10, 11, 12, and 13. Laravel 13 requires PHP 8.3.
- Authenticate with the OAuth 2.0 client credentials grant. Configure `SHOPIFY_CLIENT_ID` and `SHOPIFY_CLIENT_SECRET`.
- Laravel Boost guidelines at `resources/boost/guidelines/core.blade.php` and the `shopify-filestorage` skill.
