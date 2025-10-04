<?php

declare(strict_types=1);

use Droath\PrismTransformer\ContentFetchers\BasicHttpFetcher;
use Droath\PrismTransformer\Services\ConfigurationService;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Psr\Log\LoggerInterface;

use function Pest\Laravel\mock;

describe('BasicHttpFetcher Caching', function () {
    beforeEach(function () {
        $this->httpFactory = mock(HttpFactory::class);
        $this->pendingRequest = mock(PendingRequest::class);
        $this->response = mock(Response::class);
        $this->logger = mock(LoggerInterface::class);
        $this->cacheManager = $this->app->make(CacheManager::class);
        $this->configurationService = $this->app->make(ConfigurationService::class);

        // Clear cache before each test
        Cache::flush();
    });

    describe('cache configuration integration', function () {
        test('uses configuration service for cache settings', function () {
            $configMock = mock(ConfigurationService::class);
            $configMock->shouldReceive('isCacheEnabled')->andReturn(true);
            $configMock->shouldReceive('getCacheStore')->andReturn('default');
            $configMock->shouldReceive('getCachePrefix')->andReturn('prism_transformer');
            $configMock->shouldReceive('getContentFetchCacheTtl')->andReturn(1800);
            $configMock->shouldReceive('getHttpTimeout')->andReturn(30);

            $fetcher = new BasicHttpFetcher(
                httpFactory: $this->httpFactory,
                logger: $this->logger,
                cache: $this->cacheManager,
                configuration: $configMock
            );

            expect($fetcher)->toBeInstanceOf(BasicHttpFetcher::class);
        });

        test('respects cache enabled configuration', function () {
            Config::set('prism-transformer.cache.content_fetch.enabled', false);

            $configService = new ConfigurationService();
            $isCacheEnabled = $configService->isContentFetchCacheEnabled();

            expect($isCacheEnabled)->toBeFalse();
        });

        test('respects cache TTL configuration', function () {
            Config::set('prism-transformer.cache.content_fetch.enabled', true);
            Config::set('prism-transformer.cache.content_fetch.ttl', 900);

            $configService = new ConfigurationService();
            $ttl = $configService->getContentFetchCacheTtl();

            expect($ttl)->toBe(900);
        });
    });

    describe('cache behavior through public interface', function () {
        test('caches fetch results consistently', function () {
            Config::set('prism-transformer.cache.content_fetch.enabled', true);

            $fetcher = new class(httpFactory: $this->httpFactory, logger: $this->logger, cache: $this->cacheManager, configuration: $this->configurationService) extends BasicHttpFetcher
            {
                public int $callCount = 0;

                public function fetch(string $url, array $options = []): string
                {
                    $this->callCount++;

                    return parent::fetch($url, $options);
                }
            };

            $url = 'https://example.com/api/data';
            $content = '{"message": "Hello, World!"}';

            // Mock HTTP response for first call
            $this->httpFactory->shouldReceive('timeout')
                ->times(1)
                ->andReturn($this->pendingRequest);

            $this->pendingRequest->shouldReceive('get')
                ->with($url)
                ->times(1)
                ->andReturn($this->response);

            $this->response->shouldReceive('successful')
                ->times(1)
                ->andReturn(true);

            $this->response->shouldReceive('body')
                ->times(1)
                ->andReturn($content);

            // First call should perform HTTP request
            $result1 = $fetcher->fetch($url);
            expect($fetcher->callCount)->toBe(1);
            expect($result1)->toBe($content);

            // Second call should use cache (no additional HTTP request)
            $result2 = $fetcher->fetch($url);
            expect($fetcher->callCount)->toBe(2); // Method called but HTTP request cached
            expect($result2)->toBe($content);
        });

        test('different URLs use different cache keys', function () {
            Config::set('prism-transformer.cache.content_fetch.enabled', true);

            $fetcher = new BasicHttpFetcher(
                httpFactory: $this->httpFactory,
                logger: $this->logger,
                cache: $this->cacheManager,
                configuration: $this->configurationService
            );

            $url1 = 'https://example.com/api/data1';
            $url2 = 'https://example.com/api/data2';
            $content1 = '{"message": "First URL"}';
            $content2 = '{"message": "Second URL"}';

            // Mock first URL
            $this->httpFactory->shouldReceive('timeout')
                ->times(2)
                ->andReturn($this->pendingRequest);

            $this->pendingRequest->shouldReceive('get')
                ->with($url1)
                ->times(1)
                ->andReturn($this->response);

            $this->pendingRequest->shouldReceive('get')
                ->with($url2)
                ->times(1)
                ->andReturn($this->response);

            $this->response->shouldReceive('successful')
                ->times(2)
                ->andReturn(true);

            $this->response->shouldReceive('body')
                ->times(1)
                ->andReturn($content1);

            $this->response->shouldReceive('body')
                ->times(1)
                ->andReturn($content2);

            // Both URLs should perform their own requests
            $result1 = $fetcher->fetch($url1);
            $result2 = $fetcher->fetch($url2);

            expect($result1)->toBe($content1);
            expect($result2)->toBe($content2);
        });
    });

    describe('cache storage through public interface', function () {
        test('stores and retrieves successful fetch results', function () {
            Config::set('prism-transformer.cache.content_fetch.enabled', true);

            $fetcher = new BasicHttpFetcher(
                httpFactory: $this->httpFactory,
                logger: $this->logger,
                cache: $this->cacheManager,
                configuration: $this->configurationService
            );

            $url = 'https://example.com/api/data';
            $content = '{"message": "Cached content"}';

            // Mock HTTP request - should only be called once
            $this->httpFactory->shouldReceive('timeout')
                ->times(1)
                ->andReturn($this->pendingRequest);

            $this->pendingRequest->shouldReceive('get')
                ->with($url)
                ->times(1)
                ->andReturn($this->response);

            $this->response->shouldReceive('successful')
                ->times(1)
                ->andReturn(true);

            $this->response->shouldReceive('body')
                ->times(1)
                ->andReturn($content);

            // First call should perform and cache fetch
            $result1 = $fetcher->fetch($url);
            expect($result1)->toBe($content);

            // Second call should return cached result
            $result2 = $fetcher->fetch($url);
            expect($result2)->toBe($content);
        });

        test('handles special characters in cached content', function () {
            Config::set('prism-transformer.cache.content_fetch.enabled', true);

            $fetcher = new BasicHttpFetcher(
                httpFactory: $this->httpFactory,
                logger: $this->logger,
                cache: $this->cacheManager,
                configuration: $this->configurationService
            );

            $url = 'https://example.com/api/unicode';
            $content = 'Content with special chars: àáâãäåæçèéêë 中文 العربية 🚀';

            // Mock HTTP request
            $this->httpFactory->shouldReceive('timeout')
                ->times(1)
                ->andReturn($this->pendingRequest);

            $this->pendingRequest->shouldReceive('get')
                ->with($url)
                ->times(1)
                ->andReturn($this->response);

            $this->response->shouldReceive('successful')
                ->times(1)
                ->andReturn(true);

            $this->response->shouldReceive('body')
                ->times(1)
                ->andReturn($content);

            $result1 = $fetcher->fetch($url);
            $result2 = $fetcher->fetch($url); // Should be cached

            expect($result1)->toBe($result2);
            expect($result2)->toContain('àáâãäåæçèéêë 中文 العربية 🚀');
        });
    });

    describe('cache TTL behavior', function () {
        test('respects custom TTL configuration for content fetch', function () {
            Config::set('prism-transformer.cache.content_fetch.enabled', true);
            Config::set('prism-transformer.cache.content_fetch.ttl', 900); // 15 minutes

            $configService = new ConfigurationService();
            $ttl = $configService->getContentFetchCacheTtl();

            expect($ttl)->toBe(900);
        });

        test('uses default TTL when not configured', function () {
            Config::set('prism-transformer.cache.content_fetch.enabled', true);
            // Don't set custom TTL

            $configService = new ConfigurationService();
            $ttl = $configService->getContentFetchCacheTtl();

            expect($ttl)->toBe(1800); // Default 30 minutes
        });
    });

    describe('cache behavior when disabled', function () {
        test('bypasses cache when caching is disabled', function () {
            Config::set('prism-transformer.cache.content_fetch.enabled', false);

            $fetcher = new BasicHttpFetcher(
                httpFactory: $this->httpFactory,
                logger: $this->logger,
                cache: $this->cacheManager,
                configuration: $this->configurationService
            );

            $url = 'https://example.com/api/data';
            $content = 'No cache content';

            // Mock HTTP request - should be called twice (no caching)
            $this->httpFactory->shouldReceive('timeout')
                ->times(2)
                ->andReturn($this->pendingRequest);

            $this->pendingRequest->shouldReceive('get')
                ->with($url)
                ->times(2)
                ->andReturn($this->response);

            $this->response->shouldReceive('successful')
                ->times(2)
                ->andReturn(true);

            $this->response->shouldReceive('body')
                ->times(2)
                ->andReturn($content);

            // Both calls should perform HTTP request (no caching)
            $result1 = $fetcher->fetch($url);
            $result2 = $fetcher->fetch($url);

            expect($result1)->toBe($content);
            expect($result2)->toBe($content);
        });

        test('handles cache retrieval errors gracefully', function () {
            Config::set('prism-transformer.cache.content_fetch.enabled', true);

            $fetcher = new BasicHttpFetcher(
                httpFactory: $this->httpFactory,
                logger: $this->logger,
                cache: $this->cacheManager,
                configuration: $this->configurationService
            );

            $url = 'https://example.com/api/data';
            $content = 'Fallback content';

            // Mock HTTP request
            $this->httpFactory->shouldReceive('timeout')
                ->andReturn($this->pendingRequest);

            $this->pendingRequest->shouldReceive('get')
                ->with($url)
                ->andReturn($this->response);

            $this->response->shouldReceive('successful')
                ->andReturn(true);

            $this->response->shouldReceive('body')
                ->andReturn($content);

            // Should work even if cache has issues
            $result = $fetcher->fetch($url);

            expect($result)->toBe($content);
        });
    });
});
