# Changelog

All notable changes to `felixkerser/laravel-shopify-filestorage` are documented here.

## Unreleased

- Support Laravel 12 and 13, alongside Laravel 10 and 11.

## 1.0.0 - 2026-10-05

- Staged Shopify file uploads through `stagedUploadsCreate`, a multipart binary POST, and `fileCreate`.
- `ShopifyFileStorage::getByIds()` and `ShopifyFileStorage::delete()` for Admin file ids.
- `ShopifyUrl` helpers for CDN resize, crop, and format transforms.
- Domain exceptions for GraphQL failures, upload failures, and invalid CDN urls.
