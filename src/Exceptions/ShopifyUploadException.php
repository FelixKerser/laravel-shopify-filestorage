<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage\Exceptions;

use RuntimeException;
use Throwable;

final class ShopifyUploadException extends RuntimeException
{
    /**
     * @param  array<mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly ?string $step = null,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
