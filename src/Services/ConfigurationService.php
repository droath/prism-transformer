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
     * Get configuration for a specific provider.
     *
     * @param Provider $provider The provider to get configuration for.
     *
     * @return array<string, mixed> The provider configuration array.
     */
    public function getProviderConfig(Provider $provider): array
    {
        return $provider->getConfig();
    }

    /**
     * Get a specific configuration value for a provider.
     *
     * @param Provider $provider The provider to get configuration for.
     * @param string $key The configuration key to retrieve.
     * @param mixed $default The default value if the key doesn't exist.
     *
     * @return mixed The configuration value or default.
     */
    public function getProviderConfigValue(Provider $provider, string $key, mixed $default = null): mixed
    {
        return $provider->getConfigValue($key, $default);
    }

    /**
     * Get content fetcher configuration.
     *
     * @return array<string, mixed> The content fetcher configuration.
     */
    public function getContentFetcherConfig(): array
    {
        return config('prism-transformer.content_fetcher', []);
    }

    /**
     * Get transformation configuration.
     *
     * @return array<string, mixed> The transformation configuration.
     */
    public function getTransformationConfig(): array
    {
        return config('prism-transformer.transformation', []);
    }

    /**
     * Get cache configuration.
     *
     * @return array<string, mixed> The cache configuration.
     */
    public function getCacheConfig(): array
    {
        return config('prism-transformer.cache', []);
    }

    /**
     * Check if caching is enabled.
     *
     * @return bool True if caching is enabled, false otherwise.
     */
    public function isCacheEnabled(): bool
    {
        return (bool) config('prism-transformer.cache.enabled', true);
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
     * Get HTTP timeout for content fetching.
     *
     * @return int The timeout in seconds.
     */
    public function getHttpTimeout(): int
    {
        return (int) config('prism-transformer.content_fetcher.timeout', 30);
    }

    /**
     * Get HTTP connect timeout for content fetching.
     *
     * @return int The connect timeout in seconds.
     */
    public function getHttpConnectTimeout(): int
    {
        return (int) config('prism-transformer.content_fetcher.connect_timeout', 10);
    }

    /**
     * Get retry configuration for content fetching.
     *
     * @return array<string, mixed> The retry configuration.
     */
    public function getRetryConfig(): array
    {
        return config('prism-transformer.content_fetcher.retry', []);
    }

    /**
     * Get validation configuration for content fetching.
     *
     * @return array<string, mixed> The validation configuration.
     */
    public function getValidationConfig(): array
    {
        return config('prism-transformer.content_fetcher.validation', []);
    }

    /**
     * Get async queue name for transformations.
     *
     * @return string The queue name.
     */
    public function getAsyncQueue(): string
    {
        return config('prism-transformer.transformation.async_queue', 'default');
    }

    /**
     * Get all available providers with their configurations.
     *
     * @return array<string, array<string, mixed>> All provider configurations indexed by provider value.
     */
    public function getAllProviderConfigs(): array
    {
        $configs = [];

        foreach (Provider::cases() as $provider) {
            $configs[$provider->value] = $provider->getConfig();
        }

        return $configs;
    }

    /**
     * Validate that all required configuration sections exist.
     *
     * @return array<string> Array of missing configuration sections.
     */
    public function validateConfiguration(): array
    {
        $missing = [];
        $requiredSections = [
            'default_provider',
            'providers',
            'content_fetcher',
            'transformation',
            'cache',
        ];

        foreach ($requiredSections as $section) {
            if (! config()->has("prism-transformer.{$section}")) {
                $missing[] = $section;
            }
        }

        return $missing;
    }
}
