<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage\DTOs;

final readonly class StagedUploadTarget
{
    /**
     * @param  list<array{name: string, value: string}>  $parameters
     */
    public function __construct(
        public string $url,
        public string $resourceUrl,
        public array $parameters,
    ) {}
}
