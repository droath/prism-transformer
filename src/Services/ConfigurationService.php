<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Services;

use Droath\PrismTransformer\Enums\Provider;

/**
 * Service for managing package configuration and provider settings.
 *
 * This service provides centralized access to package configuration
 * with proper defaults and validation.
 */
class ConfigurationService
{
    /**
     * Get the default provider from configuration.
     *
     * @return Provider The default provider enum instance.
     */
    public function getDefaultProvider(): Provider
    {
        $defaultProvider = config('prism-transformer.default_provider');

        // If config returns a Provider enum, use it directly
        if ($defaultProvider instanceof Provider) {
            return $defaultProvider;
        }

        // If config returns a string, try to match it to a Provider case
        if (is_string($defaultProvider)) {
            foreach (Provider::cases() as $provider) {
                if ($provider->value === $defaultProvider) {
                    return $provider;
                }
            }
        }

        // Fallback to OpenAI if no valid provider found
        return Provider::OPENAI;
    }

    /**
     * Check if content fetch caching is enabled.
     *
     * @return bool True if content fetch caching is enabled, false otherwise.
     */
    public function isContentFetchCacheEnabled(): bool
    {
        return (bool) config('prism-transformer.cache.content_fetch.enabled', false);
    }

    /**
     * Check if transformer results caching is enabled.
     *
     * @return bool True if transformer results caching is enabled, false otherwise.
     */
    public function isTransformerResultsCacheEnabled(): bool
    {
        return (bool) config('prism-transformer.cache.transformer_results.enabled', false);
    }

    /**
     * Get the cache store name to use.
     *
     * @return string The cache store name.
     */
    public function getCacheStore(): string
    {
        return config('prism-transformer.cache.store', 'default');
    }

    /**
     * Get the cache prefix to use.
     *
     * @return string The cache prefix.
     */
    public function getCachePrefix(): string
    {
        return config('prism-transformer.cache.prefix', 'prism_transformer');
    }

    /**
     * Get the TTL for content fetch caching.
     *
     * @return int The TTL in seconds.
     */
    public function getContentFetchCacheTtl(): int
    {
        return (int) config('prism-transformer.cache.content_fetch.ttl', 1800);
    }

    /**
     * Get the TTL for transformer results caching.
     *
     * @return int The TTL in seconds.
     */
    public function getTransformerResultsCacheTtl(): int
    {
        return (int) config('prism-transformer.cache.transformer_results.ttl', 3600);
    }

    /**
     * Get HTTP timeout for content fetching.
     *
     * @return int The timeout in seconds.
     */
    public function getHttpTimeout(): int
    {
        return (int) config('prism-transformer.content_fetcher.timeout', 30);
    }

    /**
     * Get an async queue name for transformations.
     *
     * @return string|null The queue name.
     */
    public function getAsyncQueue(): ?string
    {
        return config('prism-transformer.transformation.async_queue', 'default');
    }

    /**
     * Get queue connection for transformations.
     *
     * @return string|null The queue connection name, or null to use default.
     */
    public function getQueueConnection(): ?string
    {
        return config('prism-transformer.transformation.queue_connection');
    }

    /**
     * Get job timeout for transformations.
     *
     * @return int The timeout in seconds.
     */
    public function getTimeout(): int
    {
        return (int) config('prism-transformer.transformation.timeout', 60);
    }

    /**
     * Get job retry attempts for transformations.
     *
     * @return int The number of retry attempts.
     */
    public function getTries(): int
    {
        return (int) config('prism-transformer.transformation.tries', 3);
    }

    /**
     * Get client timeout for transformations.
     *
     * @return int The timeout in seconds.
     */
    public function getClientTimeout(): int
    {
        return (int) config(
            'prism-transformer.transformation.client_options.timeout',
            180
        );
    }

    /**
     * Get client connect timeout for transformations.
     *
     * @return int The connect timeout in seconds.
     */
    public function getClientConnectTimeout(): int
    {
        return (int) config(
            'prism-transformer.transformation.client_options.connect_timeout',
            0
        );
    }

    /**
     * Get rate limiting configuration.
     *
     * @return array<string, mixed> The rate-limiting configuration.
     */
    public function getRateLimitConfig(): array
    {
        $defaults = [
            'enabled' => true,
            'max_attempts' => 60,
            'decay_minutes' => 1,
            'key_prefix' => 'prism_rate_limit',
        ];

        $config = config('prism-transformer.rate_limiting', []);

        return array_merge($defaults, $config);
    }

    /**
     * Check if rate limiting is enabled.
     *
     * @return bool True if rate limiting is enabled, false otherwise.
     */
    public function isRateLimitingEnabled(): bool
    {
        return (bool) config('prism-transformer.rate_limiting.enabled', true);
    }
}
