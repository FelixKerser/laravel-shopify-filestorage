<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage\Enums;

enum ShopifyResourceType: string
{
    case File = 'FILE';
    case Image = 'IMAGE';
    case Video = 'VIDEO';

    public static function fromMime(string $mime): self
    {
        $normalized = strtolower(trim($mime));

        return match (true) {
            str_starts_with($normalized, 'image/') => self::Image,
            str_starts_with($normalized, 'video/') => self::Video,
            default => self::File,
        };
    }
}
