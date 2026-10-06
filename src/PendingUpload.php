<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage;

use FelixKerser\ShopifyFileStorage\Actions\UploadFileAction;
use FelixKerser\ShopifyFileStorage\DTOs\ShopifyFile;
use FelixKerser\ShopifyFileStorage\Enums\ShopifyResourceType;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;

final class PendingUpload
{
    private ?ShopifyResourceType $resource = null;

    private ?string $alt = null;

    private ?string $filename = null;

    public function __construct(
        private readonly UploadFileAction $action,
        private readonly string|File|UploadedFile $file,
    ) {}

    public function asImage(): self
    {
        $this->resource = ShopifyResourceType::Image;

        return $this;
    }

    public function asVideo(): self
    {
        $this->resource = ShopifyResourceType::Video;

        return $this;
    }

    public function asFile(): self
    {
        $this->resource = ShopifyResourceType::File;

        return $this;
    }

    public function withAlt(?string $alt): self
    {
        $this->alt = $alt;

        return $this;
    }

    public function withFilename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function save(): ShopifyFile
    {
        return $this->action->execute($this->file, $this->resource, $this->alt, $this->filename);
    }
}
