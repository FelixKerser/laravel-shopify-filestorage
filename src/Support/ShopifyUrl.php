<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage\Support;

use FelixKerser\ShopifyFileStorage\Exceptions\InvalidCdnUrlException;
use InvalidArgumentException;

final class ShopifyUrl
{
    public const MAX_DIMENSION = 5760;

    private const SIZE_PATTERN = '/_(?:\d+x\d*|x\d+|pico|icon|thumb|small|compact|medium|large|grande|original|master)(?:_crop_[a-z]+)?(?=\.(?:jpe?g|png|gif|webp|avif)(?:\.(?:webp|avif|png|jpe?g))?$)/i';

    /**
     * @var list<string>
     */
    private const FORMATS = ['webp', 'avif', 'png', 'jpg'];

    /**
     * @var list<string>
     */
    private const CROPS = ['top', 'center', 'bottom', 'left', 'right'];

    public static function resize(string $url, ?int $width, ?int $height, ?string $crop = null): string
    {
        if ($width === null && $height === null) {
            throw new InvalidArgumentException('A Shopify CDN resize needs a width or a height.');
        }

        self::assertDimension($width, 'width');
        self::assertDimension($height, 'height');

        $parsed = self::parse($url);
        $path = self::stripLegacySize($parsed['path']);
        $query = self::applyQuery($parsed['query'], [
            'width' => $width === null ? null : (string) $width,
            'height' => $height === null ? null : (string) $height,
            'crop' => self::normalizeCrop($crop, $width, $height),
        ]);

        return self::build($parsed['parts'], $path, $query, $parsed['protocolRelative']);
    }

    public static function format(string $url, string $format = 'webp'): string
    {
        $format = strtolower(trim($format));

        if (! in_array($format, self::FORMATS, true)) {
            throw new InvalidArgumentException("Unsupported Shopify CDN format [{$format}]. Expected webp, avif, png, or jpg.");
        }

        $parsed = self::parse($url);
        $filename = self::applyFormat(basename($parsed['path']), $format);
        $directory = substr($parsed['path'], 0, -strlen(basename($parsed['path'])));
        $query = self::applyQuery($parsed['query'], [
            'format' => $format,
        ]);

        return self::build($parsed['parts'], $directory.$filename, $query, $parsed['protocolRelative']);
    }

    private static function assertDimension(?int $value, string $name): void
    {
        if ($value === null) {
            return;
        }

        if ($value < 1 || $value > self::MAX_DIMENSION) {
            throw new InvalidArgumentException("Shopify CDN {$name} must be between 1 and ".self::MAX_DIMENSION.'.');
        }
    }

    private static function normalizeCrop(?string $crop, ?int $width, ?int $height): ?string
    {
        if ($crop === null || trim($crop) === '') {
            return null;
        }

        $normalized = strtolower(trim($crop));

        if (! in_array($normalized, self::CROPS, true)) {
            throw new InvalidArgumentException("Unsupported Shopify CDN crop [{$normalized}].");
        }

        if ($width === null || $height === null) {
            throw new InvalidArgumentException('A Shopify CDN crop needs both a width and a height.');
        }

        return $normalized;
    }

    private static function stripLegacySize(string $path): string
    {
        $filename = basename($path);
        $stripped = preg_replace(self::SIZE_PATTERN, '', $filename);

        if (! is_string($stripped) || $stripped === '' || $stripped === $filename) {
            return $path;
        }

        $directory = substr($path, 0, -strlen($filename));

        return $directory.$stripped;
    }

    private static function applyFormat(string $filename, string $format): string
    {
        $chained = '/^(?P<stem>.+)\.(?P<ext>jpe?g|png|gif|webp|avif)\.(?P<transform>webp|avif|png|jpe?g)$/i';
        $single = '/^(?P<stem>.+)\.(?P<ext>jpe?g|png|gif|webp|avif)$/i';

        if (preg_match($chained, $filename, $matches) !== 1 && preg_match($single, $filename, $matches) !== 1) {
            throw new InvalidCdnUrlException("[{$filename}] does not point at a Shopify CDN image.");
        }

        $stem = $matches['stem'];
        $extension = strtolower($matches['ext']);

        if (in_array($format, ['webp', 'avif'], true)) {
            if ($extension === $format) {
                return $stem.'.'.$extension;
            }

            return $stem.'.'.$extension.'.'.$format;
        }

        $target = $format === 'jpg' && $extension === 'jpeg' ? 'jpeg' : $format;

        return $stem.'.'.$target;
    }

    /**
     * @return array{
     *     parts: array<string, mixed>,
     *     protocolRelative: bool,
     *     path: string,
     *     query: list<array{0: string, 1: string}>
     * }
     */
    private static function parse(string $url): array
    {
        $url = trim($url);

        if ($url === '' || preg_match('/\s/', $url) === 1) {
            throw new InvalidCdnUrlException('The Shopify CDN url is empty or contains whitespace.');
        }

        $protocolRelative = str_starts_with($url, '//');
        $parts = parse_url($protocolRelative ? 'https:'.$url : $url);

        if ($parts === false) {
            throw new InvalidCdnUrlException("[{$url}] is not a Shopify CDN url.");
        }

        /** @var array<string, mixed> $parts */
        $host = $parts['host'] ?? null;

        if (! is_string($host) || ! self::isShopifyHost($host)) {
            throw new InvalidCdnUrlException("[{$url}] is not a Shopify CDN url.");
        }

        if (! $protocolRelative) {
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));

            if (! in_array($scheme, ['http', 'https'], true)) {
                throw new InvalidCdnUrlException("[{$url}] is not a Shopify CDN url.");
            }
        }

        $path = $parts['path'] ?? null;

        if (! is_string($path) || preg_match('/\.(?:jpe?g|png|gif|webp|avif)(?:\.(?:webp|avif|png|jpe?g))?$/i', $path) !== 1) {
            throw new InvalidCdnUrlException("[{$url}] does not point at a Shopify CDN image.");
        }

        $query = $parts['query'] ?? '';

        return [
            'parts' => $parts,
            'protocolRelative' => $protocolRelative,
            'path' => $path,
            'query' => self::parseQuery(is_string($query) ? $query : ''),
        ];
    }

    private static function isShopifyHost(string $host): bool
    {
        $host = strtolower($host);

        return $host === 'shopify.com'
            || str_ends_with($host, '.shopify.com')
            || str_ends_with($host, '.myshopify.com')
            || str_ends_with($host, '.shopifycdn.net')
            || str_ends_with($host, '.shopifycdn.com');
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private static function parseQuery(string $query): array
    {
        if ($query === '') {
            return [];
        }

        $pairs = [];

        foreach (explode('&', $query) as $part) {
            if ($part === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $pairs[] = [rawurldecode($key), rawurldecode($value)];
        }

        return $pairs;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $query
     * @param  array<string, string|null>  $replacements
     * @return list<array{0: string, 1: string}>
     */
    private static function applyQuery(array $query, array $replacements): array
    {
        $keys = array_keys($replacements);
        $filtered = [];

        foreach ($query as [$key, $value]) {
            if (in_array($key, $keys, true)) {
                continue;
            }

            $filtered[] = [$key, $value];
        }

        foreach ($replacements as $key => $value) {
            if ($value === null) {
                continue;
            }

            $filtered[] = [$key, $value];
        }

        return $filtered;
    }

    /**
     * @param  array<string, mixed>  $parts
     * @param  list<array{0: string, 1: string}>  $query
     */
    private static function build(array $parts, string $path, array $query, bool $protocolRelative): string
    {
        $host = $parts['host'] ?? null;

        if (! is_string($host) || $host === '') {
            throw new InvalidCdnUrlException('The Shopify CDN url is missing a host.');
        }

        $url = $protocolRelative ? '//' : strtolower((string) ($parts['scheme'] ?? 'https')).'://';

        if (isset($parts['user']) && is_string($parts['user'])) {
            $url .= $parts['user'];

            if (isset($parts['pass']) && is_string($parts['pass'])) {
                $url .= ':'.$parts['pass'];
            }

            $url .= '@';
        }

        $url .= $host;

        if (isset($parts['port']) && is_int($parts['port'])) {
            $url .= ':'.$parts['port'];
        }

        $url .= $path;

        if ($query !== []) {
            $url .= '?'.self::stringifyQuery($query);
        }

        if (isset($parts['fragment']) && is_string($parts['fragment']) && $parts['fragment'] !== '') {
            $url .= '#'.$parts['fragment'];
        }

        return $url;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $query
     */
    private static function stringifyQuery(array $query): string
    {
        $parts = [];

        foreach ($query as [$key, $value]) {
            $encodedKey = rawurlencode($key);
            $parts[] = $value === '' ? $encodedKey.'=' : $encodedKey.'='.rawurlencode($value);
        }

        return implode('&', $parts);
    }
}
