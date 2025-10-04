<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Abstract;

use Droath\PrismTransformer\Contracts\ContentFetcherInterface;
use Droath\PrismTransformer\Services\ConfigurationService;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Abstract base class providing caching capabilities for content fetchers.
 *
 * This class implements a template method pattern for cacheable content
 * fetching. Concrete implementations should extend this class and implement
 * the abstract performFetch method to define their specific fetching logic.
 */
abstract class BaseContentFetcher implements ContentFetcherInterface
{
    public function __construct(
        protected ?CacheManager $cache = null,
        protected ?ConfigurationService $configuration = null
    ) {}

    /**
     * {@inheritDoc}
     */
    public function fetch(string $url, array $options = []): string
    {
        if ($cachedContent = $this->getCache($url, $options)) {
            return $cachedContent;
        }
        $content = $this->performFetch($url, $options);

        if ($this->isValidContent($content)) {
            $this->setCache($url, $content, $options);
        }

        return $content;
    }

    /**
     * Perform the actual content fetching operation.
     *
     * This method should be implemented by concrete fetcher classes
     * to define their specific fetching logic (HTTP, file, database, etc.).
     *
     * @param string $url The URL or identifier to fetch content from
     * @param array $options Custom options for the fetch operation
     *
     * @return string The fetched content as a string
     *
     * @throws \Throwable When content cannot be fetched
     */
    abstract protected function performFetch(string $url, array $options = []): string;

    /**
     * Validate if the content is valid for caching.
     *
     * Override this method in concrete classes to implement
     * specific content validation logic.
     *
     * @param string $content The content to validate
     *
     * @return bool True if content is valid for caching, false otherwise
     */
    protected function isValidContent(string $content): bool
    {
        return ! empty(trim($content));
    }

    /**
     * Generate a cache key for the given URL and options.
     */
    protected function cacheId(string $url, array $options = []): string
    {
        $data = $url.serialize($options);

        return hash('sha256', $data);
    }

    /**
     * Get cached content for the given URL and options.
     */
    protected function getCache(string $url, array $options = []): ?string
    {
        if (! $this->isCacheEnabled()) {
            return null;
        }

        try {
            return $this->getCacheStore()->get(
                $this->buildCacheKey($url, $options)
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Cache content for the given URL and options.
     */
    protected function setCache(
        string $url,
        string $content,
        array $options = []
    ): bool {
        if (! $this->isCacheEnabled()) {
            return false;
        }
        $ttl = $this->configuration?->getContentFetchCacheTtl();

        try {
            return $this->getCacheStore()->put(
                $this->buildCacheKey($url, $options),
                $content,
                $ttl
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if content fetch caching is enabled.
     */
    protected function isCacheEnabled(): bool
    {
        return $this->configuration !== null && $this->configuration->isContentFetchCacheEnabled();
    }

    /**
     * Get the cache store instance.
     */
    protected function getCacheStore(): Repository
    {
        if ($this->cache && $this->configuration) {
            $storeName = $this->configuration->getCacheStore();
            try {
                return $this->cache->store($storeName);
            } catch (\Throwable) {
                return Cache::store();
            }
        }

        return Cache::store();
    }

    /**
     * Build the cache key with a configured prefix.
     */
    protected function buildCacheKey(string $url, array $options = []): string
    {
        $prefix = $this->configuration?->getCachePrefix()
            ?? 'prism_transformer';

        return "{$prefix}:content_fetch:{$this->cacheId($url, $options)}";
    }
}
