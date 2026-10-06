<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage\Exceptions;

use RuntimeException;

final class ShopifyGraphQLException extends RuntimeException
{
    /**
     * @param  array<mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
        public readonly ?int $statusCode = null,
        public readonly bool $fromUserErrors = false,
    ) {
        parent::__construct($message);
    }
}
