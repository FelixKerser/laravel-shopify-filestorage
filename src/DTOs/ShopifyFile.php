<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage\DTOs;

use FelixKerser\ShopifyFileStorage\Exceptions\ShopifyGraphQLException;

final readonly class ShopifyFile
{
    public function __construct(
        public string $id,
        public ?string $url,
        public ?string $alt,
        public string $mediaType,
        public string $status,
    ) {}

    /**
     * @param  array<string, mixed>  $node
     */
    public static function fromGraphQLNode(array $node): self
    {
        $typeName = $node['__typename'] ?? null;
        $id = $node['id'] ?? null;

        if (! is_string($typeName) || ! is_string($id) || $id === '') {
            throw new ShopifyGraphQLException('Shopify returned a file without an id.');
        }

        $mediaType = match ($typeName) {
            'MediaImage' => 'IMAGE',
            'Video' => 'VIDEO',
            'GenericFile' => 'FILE',
            'Model3d' => 'MODEL_3D',
            default => throw new ShopifyGraphQLException("Shopify returned an unsupported file type [{$typeName}]."),
        };

        $status = $node['fileStatus'] ?? null;

        if (! is_string($status) || $status === '') {
            throw new ShopifyGraphQLException('Shopify returned a file without a status.');
        }

        $alt = $node['alt'] ?? null;

        return new self(
            id: $id,
            url: self::extractUrl($node),
            alt: is_string($alt) ? $alt : null,
            mediaType: $mediaType,
            status: $status,
        );
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function extractUrl(array $node): ?string
    {
        $direct = [
            self::childUrl($node, 'image'),
            self::stringValue($node['url'] ?? null),
            self::childUrl($node, 'originalSource'),
            self::previewUrl($node),
        ];

        foreach ($direct as $candidate) {
            if ($candidate !== null) {
                return $candidate;
            }
        }

        $sources = $node['sources'] ?? null;

        if (! is_array($sources)) {
            return null;
        }

        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }

            /** @var array<string, mixed> $source */
            $url = self::stringValue($source['url'] ?? null);

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function childUrl(array $node, string $key): ?string
    {
        $child = $node[$key] ?? null;

        if (! is_array($child)) {
            return null;
        }

        /** @var array<string, mixed> $child */
        return self::stringValue($child['url'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function previewUrl(array $node): ?string
    {
        $preview = $node['preview'] ?? null;

        if (! is_array($preview)) {
            return null;
        }

        /** @var array<string, mixed> $preview */
        return self::childUrl($preview, 'image');
    }

    private static function stringValue(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
