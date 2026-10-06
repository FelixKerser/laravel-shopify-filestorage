<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage\Actions;

use FelixKerser\ShopifyFileStorage\DTOs\ShopifyFile;
use FelixKerser\ShopifyFileStorage\DTOs\StagedUploadTarget;
use FelixKerser\ShopifyFileStorage\Enums\ShopifyResourceType;
use FelixKerser\ShopifyFileStorage\Exceptions\ShopifyGraphQLException;
use FelixKerser\ShopifyFileStorage\Exceptions\ShopifyUploadException;
use FelixKerser\ShopifyFileStorage\Services\ShopifyCdnClient;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Throwable;

class UploadFileAction
{
    public function __construct(private readonly ShopifyCdnClient $client) {}

    public function execute(
        string|File|UploadedFile $file,
        ?ShopifyResourceType $resource,
        ?string $alt,
        ?string $filename,
    ): ShopifyFile {
        $source = $this->describe($file, $filename);
        $resource ??= ShopifyResourceType::fromMime($source['mime']);

        try {
            $target = $this->client->createStagedUpload(
                $source['filename'],
                $source['mime'],
                $resource,
                $source['size'],
            );
        } catch (ShopifyGraphQLException $exception) {
            $this->abortUpload($exception, 'stagedUploadsCreate');
        }

        $this->uploadBinary($target, $source['contents'], $source['filename'], $source['mime']);

        try {
            return $this->client->createFile($target->resourceUrl, $resource, $alt, $source['filename']);
        } catch (ShopifyGraphQLException $exception) {
            $this->abortUpload($exception, 'fileCreate');
        }
    }

    private function abortUpload(ShopifyGraphQLException $exception, string $step): never
    {
        if (! $exception->fromUserErrors) {
            throw $exception;
        }

        throw new ShopifyUploadException($exception->getMessage(), $step, $exception->errors, $exception);
    }

    /**
     * @return array{filename: string, mime: string, contents: string, size: int}
     */
    private function describe(string|File|UploadedFile $file, ?string $filename): array
    {
        $path = $this->path($file);
        $contents = $this->contents($path);
        $size = strlen($contents);

        if ($size < 1) {
            throw new ShopifyUploadException('The file is empty.', 'read');
        }

        return [
            'filename' => $this->filename($file, $filename),
            'mime' => $this->mime($file, $path),
            'contents' => $contents,
            'size' => $size,
        ];
    }

    private function path(string|File|UploadedFile $file): string
    {
        if (is_string($file)) {
            if (! is_file($file)) {
                throw new ShopifyUploadException("File [{$file}] does not exist.", 'read');
            }

            return $file;
        }

        $path = $file->getRealPath();

        if ($path === false || ! is_file($path)) {
            $path = $file->getPathname();
        }

        if (! is_file($path)) {
            throw new ShopifyUploadException("File [{$file->getPathname()}] does not exist.", 'read');
        }

        return $path;
    }

    private function contents(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new ShopifyUploadException("Unable to read [{$path}].", 'read');
        }

        return $contents;
    }

    private function filename(string|File|UploadedFile $file, ?string $filename): string
    {
        if ($filename !== null) {
            return $this->sanitizeFilename($filename);
        }

        if ($file instanceof UploadedFile) {
            return $this->sanitizeFilename($file->getClientOriginalName());
        }

        if ($file instanceof File) {
            return $this->sanitizeFilename($file->getFilename());
        }

        return $this->sanitizeFilename(basename($file));
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', trim($filename)));

        if ($filename === '' || $filename === '.' || $filename === '..' || str_contains($filename, "\0")) {
            throw new ShopifyUploadException('The upload filename is empty.', 'read');
        }

        return $filename;
    }

    private function mime(string|File|UploadedFile $file, string $path): string
    {
        if ($file instanceof File || $file instanceof UploadedFile) {
            $detected = $file->getMimeType();

            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }

        $detected = mime_content_type($path);

        if (! is_string($detected) || $detected === '') {
            throw new ShopifyUploadException("Unable to detect a MIME type for [{$path}].", 'read');
        }

        return $detected;
    }

    private function uploadBinary(StagedUploadTarget $target, string $contents, string $filename, string $mime): void
    {
        $request = Http::timeout($this->client->timeout())
            ->connectTimeout(3)
            ->asMultipart();

        foreach ($target->parameters as $parameter) {
            $request = $request->attach($parameter['name'], $parameter['value']);
        }

        try {
            $response = $request
                ->attach('file', $contents, $filename, ['Content-Type' => $mime])
                ->post($target->url);
        } catch (Throwable $exception) {
            throw new ShopifyUploadException(
                'Shopify staged upload request failed: '.$exception->getMessage(),
                'binaryUpload',
                [],
                $exception,
            );
        }

        if (! in_array($response->status(), [200, 201, 204], true)) {
            $body = trim($response->body());

            if (strlen($body) > 500) {
                $body = substr($body, 0, 500).'...';
            }

            $detail = $body === '' ? 'the storage target returned status '.$response->status().'.' : $body;

            throw new ShopifyUploadException(
                'Shopify staged upload failed: '.$detail,
                'binaryUpload',
            );
        }
    }
}
