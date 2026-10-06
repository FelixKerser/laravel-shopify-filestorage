<?php

declare(strict_types=1);

use FelixKerser\ShopifyFileStorage\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function shopifyJpegBytes(): string
{
    $bytes = base64_decode(
        '/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxISEhUSEhMVFhUVGBcYGBgYGBgYGBgYGBgYGBgYGBgYHSggGBolGxUVITEhJSkrLi4uFx8zODMtNygtLisBCgoKDg0OGxAQGy0lICUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAcABAMBIgACEQEDEQH/xAAbAAACAwEBAQAAAAAAAAAAAAAFBgMEAQIHAP/EABQBAQAAAAAAAAAAAAAAAAAAAAD/xAAVAQEBAAAAAAAAAAAAAAAAAAAAAf/EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhEDEQA/AJe0AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD/2Q==',
        true,
    );

    if ($bytes === false) {
        throw new RuntimeException('Unable to build the JPEG fixture.');
    }

    return $bytes;
}

function shopifyFixture(string $filename, string $contents): string
{
    $directory = sys_get_temp_dir().'/laravel-shopify-filestorage';

    if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException('Unable to create the fixture directory.');
    }

    $path = $directory.'/'.$filename;
    file_put_contents($path, $contents);

    return $path;
}
