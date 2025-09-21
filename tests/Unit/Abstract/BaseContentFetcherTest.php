<?php

declare(strict_types=1);

use Droath\PrismTransformer\Abstract\BaseContentFetcher;
use Droath\PrismTransformer\Services\ConfigurationService;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\mock;

describe('BaseContentFetcher', function () {
    beforeEach(function () {
        $this->cacheManager = $this->app->make(CacheManager::class);
        $this->configurationService = $this->app->make(ConfigurationService::class);

        // Clear cache before each test
        Cache::flush();
    });

    describe('abstract class functionality', function () {
        test('can be extended to create concrete fetchers', function () {
            $fetcher = new class($this->cacheManager, $this->configurationService) extends BaseContentFetcher
            {
                protected function performFetch(string $url, array $options = []): string
                {
                    return "Fetched content from: {$url}";
                }
            };

            expect($fetcher)->toBeInstanceOf(BaseContentFetcher::class);
            expect($fetcher)->toBeInstanceOf(\Droath\PrismTransformer\Contracts\ContentFetcherInterface::class);
        });

        test('implements template method pattern for caching', function () {
            Config::set('prism-transformer.cache.enabled', true);

            $fetcher = new class($this->cacheManager, $this->configurationService) extends BaseContentFetcher
            {
                public int $callCount = 0;

                protected function performFetch(string $url, array $options = []): string
                {
                    $this->callCount++;

                    return "Content from {$url}";
                }
            };

            $url = 'https://example.com/test';

            // First call should execute performFetch
            $result1 = $fetcher->fetch($url);
            expect($fetcher->callCount)->toBe(1);
            expect($result1)->toBe('Content from https://example.com/test');

            // Second call should use cache (no additional performFetch call)
            $result2 = $fetcher->fetch($url);
            expect($fetcher->callCount)->toBe(1); // Should not increment
            expect($result2)->toBe('Content from https://example.com/test');
        });

        test('supports fetch with custom options', function () {
            Config::set('prism-transformer.cache.enabled', true);

            $fetcher = new class($this->cacheManager, $this->configurationService) extends BaseContentFetcher
            {
                public array $lastOptions = [];

                protected function performFetch(string $url, array $options = []): string
                {
                    $this->lastOptions = $options;

                    return "Content from {$url} with options";
                }
            };

            $url = 'https://example.com/test';
            $options = ['method' => 'POST', 'headers' => ['Accept' => 'application/json']];

            $result = $fetcher->fetch($url, $options);

            expect($result)->toBe('Content from https://example.com/test with options');
            expect($fetcher->lastOptions)->toBe($options);
        });
    });

    describe('cache functionality', function () {
        test('respects cache configuration', function () {
            Config::set('prism-transformer.cache.enabled', false);

            $fetcher = new class($this->cacheManager, $this->configurationService) extends BaseContentFetcher
            {
                public int $callCount = 0;

                protected function performFetch(string $url, array $options = []): string
                {
                    $this->callCount++;

                    return "No cache content from {$url}";
                }
            };

            $url = 'https://example.com/nocache';

            // Both calls should execute performFetch (no caching)
            $result1 = $fetcher->fetch($url);
            $result2 = $fetcher->fetch($url);

            expect($fetcher->callCount)->toBe(2); // Both calls executed
            expect($result1)->toBe($result2);
        });

        test('uses configuration service for cache settings', function () {
            $configMock = mock(ConfigurationService::class);
            $configMock->shouldReceive('isCacheEnabled')->andReturn(true);
            $configMock->shouldReceive('getCacheStore')->andReturn('default');
            $configMock->shouldReceive('getCachePrefix')->andReturn('test_prefix');
            $configMock->shouldReceive('getContentFetchCacheTtl')->andReturn(900);

            $fetcher = new class($this->cacheManager, $configMock) extends BaseContentFetcher
            {
                protected function performFetch(string $url, array $options = []): string
                {
                    return "Mocked content from {$url}";
                }
            };

            expect($fetcher)->toBeInstanceOf(BaseContentFetcher::class);
        });

        test('handles cache errors gracefully', function () {
            Config::set('prism-transformer.cache.enabled', true);

            $fetcher = new class($this->cacheManager, $this->configurationService) extends BaseContentFetcher
            {
                protected function performFetch(string $url, array $options = []): string
                {
                    return "Graceful content from {$url}";
                }
            };

            $url = 'https://example.com/graceful';

            // Should work even if cache has issues
            $result = $fetcher->fetch($url);

            expect($result)->toBe('Graceful content from https://example.com/graceful');
        });
    });

    describe('cache key generation', function () {
        test('generates consistent cache keys', function () {
            $fetcher = new class($this->cacheManager, $this->configurationService) extends BaseContentFetcher
            {
                protected function performFetch(string $url, array $options = []): string
                {
                    return 'Content';
                }

                // Expose protected method for testing
                public function testCacheId(string $url, array $options = []): string
                {
                    return $this->cacheId($url, $options);
                }

                public function testBuildCacheKey(string $url, array $options = []): string
                {
                    return $this->buildCacheKey($url, $options);
                }
            };

            $url = 'https://example.com/test';

            // Same URL should generate same cache ID
            $cacheId1 = $fetcher->testCacheId($url);
            $cacheId2 = $fetcher->testCacheId($url);

            expect($cacheId1)->toBe($cacheId2);
            expect($cacheId1)->toBeString();
            expect(strlen($cacheId1))->toBe(64); // SHA256 hash length

            // Cache key should include prefix
            $cacheKey = $fetcher->testBuildCacheKey($url);
            expect($cacheKey)->toContain('prism_transformer:content_fetch:');
            expect($cacheKey)->toContain($cacheId1);
        });

        test('different URLs generate different cache keys', function () {
            $fetcher = new class($this->cacheManager, $this->configurationService) extends BaseContentFetcher
            {
                protected function performFetch(string $url, array $options = []): string
                {
                    return 'Content';
                }

                public function testCacheId(string $url, array $options = []): string
                {
                    return $this->cacheId($url, $options);
                }
            };

            $url1 = 'https://example.com/test1';
            $url2 = 'https://example.com/test2';

            $cacheId1 = $fetcher->testCacheId($url1);
            $cacheId2 = $fetcher->testCacheId($url2);

            expect($cacheId1)->not->toBe($cacheId2);
        });

        test('different options generate different cache keys', function () {
            $fetcher = new class($this->cacheManager, $this->configurationService) extends BaseContentFetcher
            {
                protected function performFetch(string $url, array $options = []): string
                {
                    return 'Content';
                }

                public function testCacheId(string $url, array $options = []): string
                {
                    return $this->cacheId($url, $options);
                }
            };

            $url = 'https://example.com/test';
            $options1 = ['method' => 'GET'];
            $options2 = ['method' => 'POST'];

            $cacheId1 = $fetcher->testCacheId($url, $options1);
            $cacheId2 = $fetcher->testCacheId($url, $options2);

            expect($cacheId1)->not->toBe($cacheId2);
        });

        test('same URL and options generate same cache key', function () {
            $fetcher = new class($this->cacheManager, $this->configurationService) extends BaseContentFetcher
            {
                protected function performFetch(string $url, array $options = []): string
                {
                    return 'Content';
                }

                public function testCacheId(string $url, array $options = []): string
                {
                    return $this->cacheId($url, $options);
                }
            };

            $url = 'https://example.com/test';
            $options = ['method' => 'GET', 'headers' => ['Accept' => 'application/json']];

            $cacheId1 = $fetcher->testCacheId($url, $options);
            $cacheId2 = $fetcher->testCacheId($url, $options);

            expect($cacheId1)->toBe($cacheId2);
        });
    });

    describe('content validation', function () {
        test('caches valid content', function () {
            Config::set('prism-transformer.cache.enabled', true);

            $fetcher = new class($this->cacheManager, $this->configurationService) extends BaseContentFetcher
            {
                public int $callCount = 0;

                protected function performFetch(string $url, array $options = []): string
                {
                    $this->callCount++;

                    return "Valid content from {$url}";
                }
            };

            $url = 'https://example.com/valid';

            // First call should fetch and cache
            $result1 = $fetcher->fetch($url);
            expect($fetcher->callCount)->toBe(1);
            expect($result1)->toBe('Valid content from https://example.com/valid');

            // Second call should use cache
            $result2 = $fetcher->fetch($url);
            expect($fetcher->callCount)->toBe(1);
            expect($result2)->toBe('Valid content from https://example.com/valid');
        });

        test('does not cache empty content', function () {
            Config::set('prism-transformer.cache.enabled', true);

            $fetcher = new class($this->cacheManager, $this->configurationService) extends BaseContentFetcher
            {
                public int $callCount = 0;

                protected function performFetch(string $url, array $options = []): string
                {
                    $this->callCount++;

                    return '';
                }
            };

            $url = 'https://example.com/empty';

            // First call should fetch but not cache
            $result1 = $fetcher->fetch($url);
            expect($fetcher->callCount)->toBe(1);
            expect($result1)->toBe('');

            // Second call should fetch again (no cache)
            $result2 = $fetcher->fetch($url);
            expect($fetcher->callCount)->toBe(2);
            expect($result2)->toBe('');
        });

        test('does not cache whitespace-only content', function () {
            Config::set('prism-transformer.cache.enabled', true);

            $fetcher = new class($this->cacheManager, $this->configurationService) extends BaseContentFetcher
            {
                public int $callCount = 0;

                protected function performFetch(string $url, array $options = []): string
                {
                    $this->callCount++;

                    return "   \n\t  ";
                }
            };

            $url = 'https://example.com/whitespace';

            // First call should fetch but not cache
            $result1 = $fetcher->fetch($url);
            expect($fetcher->callCount)->toBe(1);
            expect($result1)->toBe("   \n\t  ");

            // Second call should fetch again (no cache)
            $result2 = $fetcher->fetch($url);
            expect($fetcher->callCount)->toBe(2);
            expect($result2)->toBe("   \n\t  ");
        });

        test('can override content validation', function () {
            Config::set('prism-transformer.cache.enabled', true);

            $fetcher = new class($this->cacheManager, $this->configurationService) extends BaseContentFetcher
            {
                public int $callCount = 0;

                protected function performFetch(string $url, array $options = []): string
                {
                    $this->callCount++;

                    return 'error:404';
                }

                protected function isValidContent(string $content): bool
                {
                    // Don't cache error responses
                    return ! str_starts_with($content, 'error:');
                }
            };

            $url = 'https://example.com/error';

            // First call should fetch but not cache due to custom validation
            $result1 = $fetcher->fetch($url);
            expect($fetcher->callCount)->toBe(1);
            expect($result1)->toBe('error:404');

            // Second call should fetch again (no cache)
            $result2 = $fetcher->fetch($url);
            expect($fetcher->callCount)->toBe(2);
            expect($result2)->toBe('error:404');
        });
    });

    describe('constructor flexibility', function () {
        test('can be instantiated with minimal parameters', function () {
            $fetcher = new class() extends BaseContentFetcher
            {
                protected function performFetch(string $url, array $options = []): string
                {
                    return "Minimal content from {$url}";
                }
            };

            expect($fetcher)->toBeInstanceOf(BaseContentFetcher::class);

            // Should work without caching (since no configuration provided)
            $result = $fetcher->fetch('https://example.com/minimal');
            expect($result)->toBe('Minimal content from https://example.com/minimal');
        });

        test('can be instantiated with full parameters', function () {
            $fetcher = new class($this->cacheManager, $this->configurationService) extends BaseContentFetcher
            {
                protected function performFetch(string $url, array $options = []): string
                {
                    return "Full content from {$url}";
                }
            };

            expect($fetcher)->toBeInstanceOf(BaseContentFetcher::class);

            $result = $fetcher->fetch('https://example.com/full');
            expect($result)->toBe('Full content from https://example.com/full');
        });
    });
});
