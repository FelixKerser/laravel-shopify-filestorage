<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage\Services;

use FelixKerser\ShopifyFileStorage\DTOs\ShopifyFile;
use FelixKerser\ShopifyFileStorage\DTOs\StagedUploadTarget;
use FelixKerser\ShopifyFileStorage\Enums\ShopifyResourceType;
use FelixKerser\ShopifyFileStorage\Exceptions\ShopifyGraphQLException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ShopifyCdnClient
{
    private const FILE_SELECTION = <<<'GRAPHQL'
        __typename
        ... on MediaImage {
          id
          alt
          fileStatus
          image { url }
          preview { image { url } }
        }
        ... on Video {
          id
          alt
          fileStatus
          originalSource { url }
          sources { url }
          preview { image { url } }
        }
        ... on GenericFile {
          id
          alt
          fileStatus
          url
          preview { image { url } }
        }
        ... on Model3d {
          id
          alt
          fileStatus
          originalSource { url }
          preview { image { url } }
        }
        GRAPHQL;

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function graphql(string $query, array $variables = []): array
    {
        $response = $this->request()->post($this->endpoint(), [
            'query' => $query,
            'variables' => $variables === [] ? new \stdClass : $variables,
        ]);

        if ($response->failed()) {
            throw new ShopifyGraphQLException(
                sprintf('Shopify Admin API request failed with status %d.', $response->status()),
                statusCode: $response->status(),
            );
        }

        return $this->data($response);
    }

    public function createStagedUpload(
        string $filename,
        string $mimeType,
        ShopifyResourceType $resource,
        int $fileSize,
    ): StagedUploadTarget {
        $data = $this->graphql(
            <<<'GRAPHQL'
            mutation StagedUploadsCreate($input: [StagedUploadInput!]!) {
              stagedUploadsCreate(input: $input) {
                stagedTargets {
                  url
                  resourceUrl
                  parameters { name value }
                }
                userErrors { field message }
              }
            }
            GRAPHQL,
            [
                'input' => [[
                    'filename' => $filename,
                    'mimeType' => $mimeType,
                    'resource' => $resource->value,
                    'fileSize' => (string) $fileSize,
                    'httpMethod' => 'POST',
                ]],
            ],
        );

        $payload = $this->operation($data, 'stagedUploadsCreate');
        $this->guardUserErrors($payload, 'stagedUploadsCreate');

        $targets = $payload['stagedTargets'] ?? null;
        $target = is_array($targets) ? ($targets[0] ?? null) : null;

        if (! is_array($target)) {
            throw new ShopifyGraphQLException('Shopify stagedUploadsCreate did not return an upload target.');
        }

        /** @var array<string, mixed> $target */
        return new StagedUploadTarget(
            url: $this->requiredString($target, 'url', 'staged upload url'),
            resourceUrl: $this->requiredString($target, 'resourceUrl', 'staged resource url'),
            parameters: $this->parameters($target['parameters'] ?? null),
        );
    }

    public function createFile(
        string $resourceUrl,
        ShopifyResourceType $resource,
        ?string $alt,
        string $filename,
    ): ShopifyFile {
        $file = [
            'originalSource' => $resourceUrl,
            'contentType' => $resource->value,
            'filename' => $filename,
        ];

        if ($alt !== null) {
            $file['alt'] = $alt;
        }

        $data = $this->graphql(
            <<<GRAPHQL
            mutation FileCreate(\$files: [FileCreateInput!]!) {
              fileCreate(files: \$files) {
                files {
                  {$this->fileSelection()}
                }
                userErrors { field message }
              }
            }
            GRAPHQL,
            ['files' => [$file]],
        );

        $payload = $this->operation($data, 'fileCreate');
        $this->guardUserErrors($payload, 'fileCreate');

        $files = $payload['files'] ?? null;
        $node = is_array($files) ? ($files[0] ?? null) : null;

        if (! is_array($node)) {
            throw new ShopifyGraphQLException('Shopify fileCreate did not return a file.');
        }

        /** @var array<string, mixed> $node */
        return ShopifyFile::fromGraphQLNode($node);
    }

    /**
     * @param  list<string>  $ids
     * @return list<ShopifyFile>
     */
    public function filesByIds(array $ids): array
    {
        $ids = $this->normalizeIds($ids);

        if ($ids === []) {
            return [];
        }

        $data = $this->graphql(
            <<<GRAPHQL
            query ShopifyFiles(\$ids: [ID!]!) {
              nodes(ids: \$ids) {
                {$this->fileSelection()}
              }
            }
            GRAPHQL,
            ['ids' => $ids],
        );

        $nodes = $data['nodes'] ?? null;

        if (! is_array($nodes)) {
            throw new ShopifyGraphQLException('Shopify did not return file nodes.');
        }

        /** @var array<string, ShopifyFile> $indexed */
        $indexed = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            /** @var array<string, mixed> $node */
            $typeName = $node['__typename'] ?? null;

            if (! is_string($typeName) || ! in_array($typeName, ['MediaImage', 'Video', 'GenericFile', 'Model3d'], true)) {
                continue;
            }

            $file = ShopifyFile::fromGraphQLNode($node);
            $indexed[$file->id] = $file;
        }

        $files = [];

        foreach ($ids as $id) {
            if (isset($indexed[$id])) {
                $files[] = $indexed[$id];
            }
        }

        return $files;
    }

    /**
     * @param  list<string>  $ids
     */
    public function deleteFiles(array $ids): bool
    {
        $ids = $this->normalizeIds($ids);

        if ($ids === []) {
            return true;
        }

        $data = $this->graphql(
            <<<'GRAPHQL'
            mutation FileDelete($fileIds: [ID!]!) {
              fileDelete(fileIds: $fileIds) {
                deletedFileIds
                userErrors { field message }
              }
            }
            GRAPHQL,
            ['fileIds' => $ids],
        );

        $payload = $this->operation($data, 'fileDelete');
        $this->guardUserErrors($payload, 'fileDelete');

        $deleted = $payload['deletedFileIds'] ?? null;

        if (! is_array($deleted)) {
            throw new ShopifyGraphQLException('Shopify fileDelete did not return deleted file ids.');
        }

        /** @var list<string> $deletedIds */
        $deletedIds = array_values(array_filter($deleted, static fn (mixed $id): bool => is_string($id)));
        $missing = array_values(array_diff($ids, $deletedIds));

        if ($missing !== []) {
            throw new ShopifyGraphQLException('Shopify did not delete every requested file.');
        }

        return true;
    }

    public function timeout(): int
    {
        $timeout = (int) config('shopify-filestorage.timeout', 30);

        return $timeout > 0 ? $timeout : 30;
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken(),
        ])
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout())
            ->connectTimeout(3);
    }

    private function endpoint(): string
    {
        $domain = strtolower(trim((string) config('shopify-filestorage.shop_domain')));
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = rtrim($domain, '/');

        if ($domain === '' || str_contains($domain, '/')) {
            throw new ShopifyGraphQLException('Shopify shop domain is not configured.');
        }

        $version = trim((string) config('shopify-filestorage.api_version', '2026-01'));

        if ($version === '') {
            throw new ShopifyGraphQLException('Shopify API version is not configured.');
        }

        return sprintf('https://%s/admin/api/%s/graphql.json', $domain, $version);
    }

    private function accessToken(): string
    {
        $token = trim((string) config('shopify-filestorage.access_token'));

        if ($token === '') {
            throw new ShopifyGraphQLException('Shopify access token is not configured.');
        }

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Response $response): array
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new ShopifyGraphQLException('Shopify Admin API returned an empty GraphQL payload.');
        }

        /** @var array<string, mixed> $payload */
        $errors = $payload['errors'] ?? null;

        if (is_array($errors) && $errors !== []) {
            throw new ShopifyGraphQLException(
                $this->messages($errors, 'Shopify Admin API returned a GraphQL error.'),
                errors: $errors,
                statusCode: $response->status(),
            );
        }

        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new ShopifyGraphQLException('Shopify Admin API returned an empty GraphQL payload.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function operation(array $data, string $name): array
    {
        $payload = $data[$name] ?? null;

        if (! is_array($payload)) {
            throw new ShopifyGraphQLException("Shopify {$name} returned an empty payload.");
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function guardUserErrors(array $payload, string $operation): void
    {
        $errors = $payload['userErrors'] ?? null;

        if (! is_array($errors) || $errors === []) {
            return;
        }

        throw new ShopifyGraphQLException(
            $this->messages($errors, "Shopify {$operation} failed."),
            errors: $errors,
            fromUserErrors: true,
        );
    }

    /**
     * @param  array<mixed>  $errors
     */
    private function messages(array $errors, string $fallback): string
    {
        $messages = [];

        foreach ($errors as $error) {
            if (! is_array($error)) {
                continue;
            }

            $message = $error['message'] ?? null;

            if (is_string($message) && $message !== '') {
                $messages[] = $message;
            }
        }

        if ($messages === []) {
            return $fallback;
        }

        return implode(' ', $messages);
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    private function parameters(mixed $parameters): array
    {
        if (! is_array($parameters)) {
            throw new ShopifyGraphQLException('Shopify staged upload target is missing parameters.');
        }

        $normalized = [];

        foreach ($parameters as $parameter) {
            if (! is_array($parameter)) {
                throw new ShopifyGraphQLException('Shopify staged upload returned an invalid parameter.');
            }

            $name = $parameter['name'] ?? null;
            $value = $parameter['value'] ?? null;

            if (! is_string($name) || $name === '' || (! is_string($value) && ! is_numeric($value))) {
                throw new ShopifyGraphQLException('Shopify staged upload returned an invalid parameter.');
            }

            if (strtolower($name) === 'file') {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'value' => (string) $value,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requiredString(array $payload, string $key, string $label): string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new ShopifyGraphQLException("Shopify staged upload is missing a {$label}.");
        }

        return $value;
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            if (! is_string($id) || trim($id) === '') {
                throw new ShopifyGraphQLException('Shopify file ids must be non-empty strings.');
            }

            $normalized[] = $id;
        }

        $normalized = array_values(array_unique($normalized));

        if (count($normalized) > 250) {
            throw new ShopifyGraphQLException('Shopify accepts at most 250 file ids per request.');
        }

        return $normalized;
    }

    private function fileSelection(): string
    {
        return trim(self::FILE_SELECTION);
    }
}
