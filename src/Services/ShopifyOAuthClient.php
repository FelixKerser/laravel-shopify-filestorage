<?php

declare(strict_types=1);

namespace FelixKerser\ShopifyFileStorage\Services;

use FelixKerser\ShopifyFileStorage\DTOs\ShopifyStoreConfig;
use FelixKerser\ShopifyFileStorage\Exceptions\ShopifyGraphQLException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ShopifyOAuthClient
{
    private const REFRESH_SKEW_SECONDS = 60;

    public function __construct(private readonly ShopifyStoreConfig $config) {}

    public function host(): string
    {
        return $this->config->host();
    }

    public function accessToken(): string
    {
        $host = $this->host();
        $clientId = $this->config->clientId;
        $clientSecret = $this->config->clientSecret;
        $cacheKey = self::cacheKey($host, $clientId, $clientSecret);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout($this->config->timeout)
            ->connectTimeout(3)
            ->post($this->tokenEndpoint($host), [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        if ($response->failed()) {
            throw new ShopifyGraphQLException(
                $this->tokenFailureMessage($response->status(), $response->json()),
                statusCode: $response->status(),
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new ShopifyGraphQLException(
                'Shopify OAuth token response did not include an access token.',
                statusCode: $response->status(),
            );
        }

        $token = $payload['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new ShopifyGraphQLException(
                'Shopify OAuth token response did not include an access token.',
                statusCode: $response->status(),
            );
        }

        $ttl = $this->cacheTtl($payload['expires_in'] ?? null);

        if ($ttl > 0) {
            Cache::put($cacheKey, $token, $ttl);
        }

        return $token;
    }

    public static function cacheKey(string $host, string $clientId, string $clientSecret): string
    {
        return 'shopify-filestorage.oauth.'.hash('sha256', $host.'|'.$clientId.'|'.$clientSecret);
    }

    private function tokenEndpoint(string $host): string
    {
        return sprintf('https://%s/admin/oauth/access_token', $host);
    }

    private function cacheTtl(mixed $expiresIn): int
    {
        if (! is_int($expiresIn) && ! (is_string($expiresIn) && is_numeric($expiresIn))) {
            $expiresIn = 86399;
        }

        $expiresIn = (int) $expiresIn;

        if ($expiresIn <= 0) {
            return 0;
        }

        if ($expiresIn > self::REFRESH_SKEW_SECONDS) {
            return $expiresIn - self::REFRESH_SKEW_SECONDS;
        }

        return $expiresIn;
    }

    private function tokenFailureMessage(int $status, mixed $payload): string
    {
        $message = sprintf('Shopify OAuth token request failed with status %d.', $status);

        if (! is_array($payload)) {
            return $message;
        }

        $error = $payload['error'] ?? null;

        if (! is_string($error) || $error === '') {
            return $message;
        }

        return $message.' '.$error;
    }
}
