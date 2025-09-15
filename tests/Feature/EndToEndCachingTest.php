<?php

declare(strict_types=1);

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\ContentFetchers\BasicHttpFetcher;
use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\ValueObjects\TransformerMetadata;
use Illuminate\Cache\CacheManager;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Psr\Log\LoggerInterface;

use function Pest\Laravel\mock;

describe('End-to-End Caching Integration', function () {
    beforeEach(function () {
        // Clear all caches before each test
        Cache::flush();

        // Set up caching configuration
        Config::set('prism-transformer.cache.enabled', true);
        Config::set('prism-transformer.cache.store', 'default');
        Config::set('prism-transformer.cache.prefix', 'test_prism');
        Config::set('prism-transformer.cache.ttl.transformer_data', 3600);
        Config::set('prism-transformer.cache.ttl.content_fetch', 1800);

        $this->cacheManager = $this->app->make(CacheManager::class);
        $this->configurationService = $this->app->make(ConfigurationService::class);
    });

    describe('transformer and content fetcher caching together', function () {
        test('complete workflow caches both content fetching and transformation', function () {
            // Mock HTTP factory and logger for content fetcher
            $httpFactory = mock(HttpFactory::class);
            $logger = mock(LoggerInterface::class);

            // Create content fetcher with caching
            $contentFetcher = new BasicHttpFetcher(
                $httpFactory,
                $logger,
                $this->cacheManager,
                $this->configurationService
            );

            // Create transformer with caching
            $transformer = new class($this->cacheManager, $this->configurationService) extends BaseTransformer
            {
                public int $transformationCallCount = 0;

                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    $this->transformationCallCount++;

                    return TransformerResult::successful(
                        "Transformed: {$content}",
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            static::class
                        )
                    );
                }
            };

            // Mock HTTP responses for content fetching
            $pendingRequest = mock(\Illuminate\Http\Client\PendingRequest::class);
            $response = mock(\Illuminate\Http\Client\Response::class);

            $httpFactory->shouldReceive('timeout')
                ->times(1)
                ->andReturn($pendingRequest);

            $pendingRequest->shouldReceive('get')
                ->with('https://example.com/content')
                ->times(1)
                ->andReturn($response);

            $response->shouldReceive('successful')
                ->times(1)
                ->andReturn(true);

            $response->shouldReceive('body')
                ->times(1)
                ->andReturn('Original content from URL');

            // First workflow execution - should fetch and transform
            $fetchedContent = $contentFetcher->fetch('https://example.com/content');
            $transformedResult1 = $transformer->execute($fetchedContent);

            expect($fetchedContent)->toBe('Original content from URL');
            expect($transformedResult1->data)->toBe('Transformed: Original content from URL');
            expect($transformer->transformationCallCount)->toBe(1);

            // Second workflow execution - should use both caches
            $fetchedContent2 = $contentFetcher->fetch('https://example.com/content');
            $transformedResult2 = $transformer->execute($fetchedContent2);

            // Content fetcher should use cache (no additional HTTP calls)
            expect($fetchedContent2)->toBe('Original content from URL');
            // Transformer should use cache (no additional transformations)
            expect($transformedResult2->data)->toBe('Transformed: Original content from URL');
            expect($transformer->transformationCallCount)->toBe(1); // No increment
        });

        test('different URLs and content produce separate cache entries', function () {
            $httpFactory = mock(HttpFactory::class);
            $logger = mock(LoggerInterface::class);

            $contentFetcher = new BasicHttpFetcher(
                $httpFactory,
                $logger,
                $this->cacheManager,
                $this->configurationService
            );

            // Use two different transformer instances to ensure different cache keys
            $transformer1 = new class($this->cacheManager, $this->configurationService) extends BaseTransformer
            {
                public int $transformationCallCount = 0;

                public function prompt(): string
                {
                    return 'Transform content from URL 1';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    $this->transformationCallCount++;

                    return TransformerResult::successful(
                        "Transformed: {$content}",
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            static::class
                        )
                    );
                }
            };

            $transformer2 = new class($this->cacheManager, $this->configurationService) extends BaseTransformer
            {
                public int $transformationCallCount = 0;

                public function prompt(): string
                {
                    return 'Transform content from URL 2';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    $this->transformationCallCount++;

                    return TransformerResult::successful(
                        "Transformed: {$content}",
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            static::class
                        )
                    );
                }
            };

            // Mock responses for two different URLs
            $pendingRequest = mock(\Illuminate\Http\Client\PendingRequest::class);
            $response1 = mock(\Illuminate\Http\Client\Response::class);
            $response2 = mock(\Illuminate\Http\Client\Response::class);

            $httpFactory->shouldReceive('timeout')
                ->times(2)
                ->andReturn($pendingRequest);

            $pendingRequest->shouldReceive('get')
                ->with('https://example.com/content1')
                ->times(1)
                ->andReturn($response1);

            $pendingRequest->shouldReceive('get')
                ->with('https://example.com/content2')
                ->times(1)
                ->andReturn($response2);

            $response1->shouldReceive('successful')->times(1)->andReturn(true);
            $response1->shouldReceive('body')->times(1)->andReturn('Content from URL 1');

            $response2->shouldReceive('successful')->times(1)->andReturn(true);
            $response2->shouldReceive('body')->times(1)->andReturn('Content from URL 2');

            // Fetch and transform content from two different sources
            $content1 = $contentFetcher->fetch('https://example.com/content1');
            $result1 = $transformer1->execute($content1);

            $content2 = $contentFetcher->fetch('https://example.com/content2');
            $result2 = $transformer2->execute($content2);

            // Both should have been processed (different cache keys)
            expect($transformer1->transformationCallCount)->toBe(1);
            expect($transformer2->transformationCallCount)->toBe(1);
            expect($result1->data)->toBe('Transformed: Content from URL 1');
            expect($result2->data)->toBe('Transformed: Content from URL 2');

            // Subsequent calls should use cache
            $content1b = $contentFetcher->fetch('https://example.com/content1');
            $result1b = $transformer1->execute($content1b);

            expect($transformer1->transformationCallCount)->toBe(1); // No increment
            expect($result1b->data)->toBe('Transformed: Content from URL 1');
        });
    });

    describe('cache configuration affects entire system', function () {
        test('disabling cache affects both content fetching and transformation', function () {
            Config::set('prism-transformer.cache.enabled', false);

            $httpFactory = mock(HttpFactory::class);
            $logger = mock(LoggerInterface::class);

            $contentFetcher = new BasicHttpFetcher(
                $httpFactory,
                $logger,
                $this->cacheManager,
                $this->configurationService
            );

            $transformer = new class($this->cacheManager, $this->configurationService) extends BaseTransformer
            {
                public int $transformationCallCount = 0;

                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    $this->transformationCallCount++;

                    return TransformerResult::successful(
                        "Transformed: {$content}",
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            static::class
                        )
                    );
                }
            };

            // Mock HTTP responses - should be called twice (no caching)
            $pendingRequest = mock(\Illuminate\Http\Client\PendingRequest::class);
            $response = mock(\Illuminate\Http\Client\Response::class);

            $httpFactory->shouldReceive('timeout')
                ->times(2)
                ->andReturn($pendingRequest);

            $pendingRequest->shouldReceive('get')
                ->with('https://example.com/nocache')
                ->times(2)
                ->andReturn($response);

            $response->shouldReceive('successful')
                ->times(2)
                ->andReturn(true);

            $response->shouldReceive('body')
                ->times(2)
                ->andReturn('Uncached content');

            // Execute workflow twice
            $content1 = $contentFetcher->fetch('https://example.com/nocache');
            $result1 = $transformer->execute($content1);

            $content2 = $contentFetcher->fetch('https://example.com/nocache');
            $result2 = $transformer->execute($content2);

            // Both HTTP fetch and transformation should have been called twice
            expect($transformer->transformationCallCount)->toBe(2);
            expect($result1->data)->toBe('Transformed: Uncached content');
            expect($result2->data)->toBe('Transformed: Uncached content');
        });

        test('different TTL settings are respected by each cache type', function () {
            // Set very short TTLs for testing
            Config::set('prism-transformer.cache.ttl.transformer_data', 1);
            Config::set('prism-transformer.cache.ttl.content_fetch', 2);

            $configService = new ConfigurationService();

            expect($configService->getTransformerDataCacheTtl())->toBe(1);
            expect($configService->getContentFetchCacheTtl())->toBe(2);
        });
    });

    describe('cache performance characteristics', function () {
        test('cache keys are properly isolated between different operations', function () {
            $transformer1 = new class($this->cacheManager, $this->configurationService) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'First transformer prompt';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    return TransformerResult::successful(
                        "First: {$content}",
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            static::class
                        )
                    );
                }
            };

            $transformer2 = new class($this->cacheManager, $this->configurationService) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Second transformer prompt';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    return TransformerResult::successful(
                        "Second: {$content}",
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            static::class
                        )
                    );
                }
            };

            $content = 'Test content';

            // Different transformers should produce different results
            $result1 = $transformer1->execute($content);
            $result2 = $transformer2->execute($content);

            expect($result1->data)->toBe('First: Test content');
            expect($result2->data)->toBe('Second: Test content');

            // Each should maintain its own cache
            $result1b = $transformer1->execute($content);
            $result2b = $transformer2->execute($content);

            expect($result1b->data)->toBe('First: Test content');
            expect($result2b->data)->toBe('Second: Test content');
        });

        test('metadata is preserved through caching process', function () {
            $transformer = new class($this->cacheManager, $this->configurationService) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Metadata preservation test';
                }

                public function provider(): Provider
                {
                    return Provider::ANTHROPIC;
                }

                public function model(): string
                {
                    return 'claude-3-sonnet';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    return TransformerResult::successful(
                        "Processed: {$content}",
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            static::class
                        )
                    );
                }
            };

            $content = 'Test content for metadata';

            // First call - should create cache entry
            $result1 = $transformer->execute($content);

            expect($result1->metadata)->not->toBeNull();
            expect($result1->metadata->model)->toBe('claude-3-sonnet');
            expect($result1->metadata->provider)->toBe(Provider::ANTHROPIC);

            // Second call - should retrieve from cache with metadata intact
            $result2 = $transformer->execute($content);

            expect($result2->metadata)->not->toBeNull();
            expect($result2->metadata->model)->toBe('claude-3-sonnet');
            expect($result2->metadata->provider)->toBe(Provider::ANTHROPIC);
            expect($result2->data)->toBe($result1->data);
        });
    });
});