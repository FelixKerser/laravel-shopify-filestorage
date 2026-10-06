<?php

declare(strict_types=1);

use FelixKerser\ShopifyFileStorage\Exceptions\InvalidCdnUrlException;
use FelixKerser\ShopifyFileStorage\Support\ShopifyUrl;

$cdn = 'https://cdn.shopify.com/s/files/1/0001/0002/files/banner.jpg';

test('resize appends width and height and keeps the existing version parameter', function () use ($cdn) {
    $url = ShopifyUrl::resize($cdn.'?v=1710000000', 800, 600);

    expect($url)->toBe($cdn.'?v=1710000000&width=800&height=600');
});

test('resize writes only the dimension that was provided', function () use ($cdn) {
    expect(ShopifyUrl::resize($cdn.'?v=1', 800, null))->toBe($cdn.'?v=1&width=800')
        ->and(ShopifyUrl::resize($cdn.'?v=1', null, 600))->toBe($cdn.'?v=1&height=600');
});

test('resize replaces existing size query parameters and appends a crop', function () use ($cdn) {
    $url = ShopifyUrl::resize($cdn.'?v=1&width=10&height=10&crop=top&utm=spring', 400, 400, 'center');

    expect($url)->toBe($cdn.'?v=1&utm=spring&width=400&height=400&crop=center');
});

test('resize strips a legacy filename size before writing query parameters', function () use ($cdn) {
    $sized = 'https://cdn.shopify.com/s/files/1/0001/0002/files/banner_300x200_crop_center.jpg?v=171';

    expect(ShopifyUrl::resize($sized, 800, null))->toBe($cdn.'?v=171&width=800');
});

test('resize strips named sizes, width-only suffixes, and height-only suffixes', function () {
    expect(ShopifyUrl::resize('https://cdn.shopify.com/s/files/1/1/files/banner_large.png', 100, null))
        ->toBe('https://cdn.shopify.com/s/files/1/1/files/banner.png?width=100')
        ->and(ShopifyUrl::resize('https://cdn.shopify.com/s/files/1/1/files/banner_100x.jpg', null, 80))
        ->toBe('https://cdn.shopify.com/s/files/1/1/files/banner.jpg?height=80')
        ->and(ShopifyUrl::resize('https://cdn.shopify.com/s/files/1/1/files/banner_x100.jpg', 50, null))
        ->toBe('https://cdn.shopify.com/s/files/1/1/files/banner.jpg?width=50');
});

test('resize keeps a chained webp extension and the format query', function () use ($cdn) {
    $url = ShopifyUrl::resize($cdn.'.webp?v=1&format=webp', 100, 100, 'CENTER');

    expect($url)->toBe($cdn.'.webp?v=1&format=webp&width=100&height=100&crop=center');
});

test('resize accepts a protocol-relative myshopify cdn url', function () {
    $url = ShopifyUrl::resize('//staleks.myshopify.com/cdn/shop/files/banner.jpg?v=9', 320, null);

    expect($url)->toBe('//staleks.myshopify.com/cdn/shop/files/banner.jpg?v=9&width=320');
});

test('resize keeps the fragment and an explicit http scheme', function () {
    $url = ShopifyUrl::resize('http://cdn.shopify.com/s/files/1/1/files/banner.jpg?v=1#hero', 10, 10);

    expect($url)->toBe('http://cdn.shopify.com/s/files/1/1/files/banner.jpg?v=1&width=10&height=10#hero');
});

test('format appends a webp extension and a format query without dropping other parameters', function () use ($cdn) {
    $url = ShopifyUrl::format($cdn.'?v=1710000000&width=400', 'webp');

    expect($url)->toBe($cdn.'.webp?v=1710000000&width=400&format=webp');
});

test('webp extension formatting is idempotent', function () use ($cdn) {
    $once = ShopifyUrl::format($cdn.'?v=1', 'webp');
    $twice = ShopifyUrl::format($once, 'WEBP');

    expect($once)->toBe($cdn.'.webp?v=1&format=webp')
        ->and($twice)->toBe($once);
});

test('format keeps an existing size suffix and can replace a chained webp extension', function () {
    $sized = 'https://cdn.shopify.com/s/files/1/1/files/banner_100x100.png?v=1';

    expect(ShopifyUrl::format($sized, 'webp'))
        ->toBe('https://cdn.shopify.com/s/files/1/1/files/banner_100x100.png.webp?v=1&format=webp')
        ->and(ShopifyUrl::format('https://cdn.shopify.com/s/files/1/1/files/banner.jpg.webp?v=1', 'jpg'))
        ->toBe('https://cdn.shopify.com/s/files/1/1/files/banner.jpg?v=1&format=jpg')
        ->and(ShopifyUrl::format('https://cdn.shopify.com/s/files/1/1/files/banner.png', 'avif'))
        ->toBe('https://cdn.shopify.com/s/files/1/1/files/banner.png.avif?format=avif')
        ->and(ShopifyUrl::format('https://cdn.shopify.com/s/files/1/1/files/banner.jpeg', 'jpg'))
        ->toBe('https://cdn.shopify.com/s/files/1/1/files/banner.jpeg?format=jpg');
});

test('format and resize compose', function () use ($cdn) {
    $url = ShopifyUrl::format(ShopifyUrl::resize($cdn.'?v=1', 1600, 900, 'center'), 'webp');

    expect($url)->toBe($cdn.'.webp?v=1&width=1600&height=900&crop=center&format=webp');
});

test('resize rejects a missing dimension, a crop without both dimensions, and an out of range size', function () use ($cdn) {
    expect(fn () => ShopifyUrl::resize($cdn, null, null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ShopifyUrl::resize($cdn, 100, null, 'center'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ShopifyUrl::resize($cdn, 0, 10))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ShopifyUrl::resize($cdn, 5761, 10))->toThrow(InvalidArgumentException::class)
        ->and(fn () => ShopifyUrl::resize($cdn, 10, 10, 'diagonal'))->toThrow(InvalidArgumentException::class);
});

test('format rejects an unsupported image format', function () use ($cdn) {
    expect(fn () => ShopifyUrl::format($cdn, 'gif'))->toThrow(InvalidArgumentException::class);
});

test('cdn helpers reject urls that are not shopify images', function () {
    expect(fn () => ShopifyUrl::resize('https://example.com/banner.jpg', 10, 10))->toThrow(InvalidCdnUrlException::class)
        ->and(fn () => ShopifyUrl::format('https://cdn.shopify.com/admin', 'webp'))->toThrow(InvalidCdnUrlException::class)
        ->and(fn () => ShopifyUrl::resize('   ', 10, 10))->toThrow(InvalidCdnUrlException::class)
        ->and(fn () => ShopifyUrl::format('not a url', 'png'))->toThrow(InvalidCdnUrlException::class);
});
