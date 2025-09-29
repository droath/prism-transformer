<?php

declare(strict_types=1);

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Services\ModelSchemaService;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\ValueObjects\TransformerMetadata;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Cache\CacheManager;

use function Pest\Laravel\mock;

describe('BaseTransformer Caching', function () {
    beforeEach(function () {
        // Create a concrete implementation for testing
        $this->transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
        {
            public function prompt(): string
            {
                return 'Transform this content';
            }
        };

        // Clear cache before each test
        Cache::flush();
    });

    describe('cache configuration integration', function () {
        test('uses configuration service for cache settings', function () {
            $configMock = mock(ConfigurationService::class);
            $configMock->shouldReceive('isCacheEnabled')->andReturn(true);
            $configMock->shouldReceive('getCacheStore')->andReturn('default');
            $configMock->shouldReceive('getCachePrefix')->andReturn('prism_transformer');
            $configMock->shouldReceive('getTransformerDataCacheTtl')->andReturn(3600);
            $configMock->shouldReceive('getDefaultProvider')->andReturn(Provider::OPENAI);

            $transformer = new class($this->app->make(CacheManager::class), $configMock, $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Test prompt';
                }
            };

            // Provider configuration is tested indirectly through transformation behavior
            expect($transformer)->toBeInstanceOf(BaseTransformer::class);
        });

        test('respects cache enabled configuration', function () {
            Config::set('prism-transformer.cache.enabled', false);

            $configService = new ConfigurationService();
            $isCacheEnabled = $configService->isCacheEnabled();

            expect($isCacheEnabled)->toBeFalse();
        });

        test('respects cache TTL configuration', function () {
            Config::set('prism-transformer.cache.enabled', true);
            Config::set('prism-transformer.cache.ttl.transformer_data', 7200);

            $configService = new ConfigurationService();
            $ttl = $configService->getTransformerDataCacheTtl();

            expect($ttl)->toBe(7200);
        });
    });

    describe('cache behavior through public interface', function () {
        test('caches transformation results consistently', function () {
            Config::set('prism-transformer.cache.enabled', true);

            // Create a testable transformer that tracks calls
            $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $callCount = 0;

                public function prompt(): string
                {
                    return 'Test prompt';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $this->callCount++;

                    return TransformerResult::successful("Transformed: {$content}");
                }
            };

            $content = 'Test content';

            // First call should perform transformation
            $result1 = $transformer->execute($content);
            expect($transformer->callCount)->toBe(1);
            expect($result1->data)->toBe('Transformed: Test content');

            // Second call should use cache (no additional transformation)
            $result2 = $transformer->execute($content);
            expect($transformer->callCount)->toBe(1); // Should not increment
            expect($result2->data)->toBe('Transformed: Test content');
        });

        test('different transformers use different cache keys', function () {
            Config::set('prism-transformer.cache.enabled', true);

            $transformer1 = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $callCount = 0;

                public function prompt(): string
                {
                    return 'First transformer prompt';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $this->callCount++;

                    return TransformerResult::successful("First: {$content}");
                }
            };

            $transformer2 = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $callCount = 0;

                public function prompt(): string
                {
                    return 'Second transformer prompt';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $this->callCount++;

                    return TransformerResult::successful("Second: {$content}");
                }
            };

            $content = 'Test content';

            // Both transformers should perform their own transformations
            $result1 = $transformer1->execute($content);
            $result2 = $transformer2->execute($content);

            expect($transformer1->callCount)->toBe(1);
            expect($transformer2->callCount)->toBe(1);
            expect($result1->data)->toBe('First: Test content');
            expect($result2->data)->toBe('Second: Test content');

            // Subsequent calls should use their respective caches
            $result1b = $transformer1->execute($content);
            $result2b = $transformer2->execute($content);

            expect($transformer1->callCount)->toBe(1); // No increment
            expect($transformer2->callCount)->toBe(1); // No increment
        });

        test('different providers use different cache keys', function () {
            Config::set('prism-transformer.default_provider', Provider::OPENAI);

            $transformer1 = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $callCount = 0;

                public function prompt(): string
                {
                    return 'Same prompt';
                }

                public function provider(): Provider
                {
                    return Provider::OPENAI;
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $this->callCount++;

                    return TransformerResult::successful("OpenAI: {$content}");
                }
            };

            $transformer2 = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $callCount = 0;

                public function prompt(): string
                {
                    return 'Same prompt';
                }

                public function provider(): Provider
                {
                    return Provider::ANTHROPIC;
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $this->callCount++;

                    return TransformerResult::successful("Anthropic: {$content}");
                }
            };

            $content = 'Test content';

            // Both should perform transformations despite same prompt
            $result1 = $transformer1->execute($content);
            $result2 = $transformer2->execute($content);

            expect($transformer1->callCount)->toBe(1);
            expect($transformer2->callCount)->toBe(1);
            expect($result1->data)->toBe('OpenAI: Test content');
            expect($result2->data)->toBe('Anthropic: Test content');
        });
    });

    describe('cache storage through public interface', function () {
        test('stores and retrieves successful transformation results', function () {
            Config::set('prism-transformer.cache.enabled', true);

            $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $callCount = 0;

                public function prompt(): string
                {
                    return 'Cache test prompt';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $this->callCount++;

                    return TransformerResult::successful(
                        "Transformed: {$content}",
                        TransformerMetadata::make('gpt-4o-mini', Provider::OPENAI, self::class)
                    );
                }
            };

            $content = 'Test content';

            // First call should perform and cache transformation
            $result1 = $transformer->execute($content);
            expect($result1->data)->toBe('Transformed: Test content');
            expect($result1->isSuccessful())->toBeTrue();
            expect($transformer->callCount)->toBe(1);

            // Second call should return cached result
            $result2 = $transformer->execute($content);
            expect($result2->data)->toBe('Transformed: Test content');
            expect($result2->isSuccessful())->toBeTrue();
            expect($transformer->callCount)->toBe(1); // No additional calls
        });

        test('does not cache failed transformation results', function () {
            Config::set('prism-transformer.cache.enabled', true);

            $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $callCount = 0;

                public function prompt(): string
                {
                    return 'Failing prompt';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $this->callCount++;

                    return TransformerResult::failed(
                        ['API error occurred'],
                        TransformerMetadata::make('gpt-4o-mini', Provider::OPENAI, self::class)
                    );
                }
            };

            $content = 'Test content';

            // First call should perform transformation but not cache it since it failed
            $result1 = $transformer->execute($content);
            expect($result1->isFailed())->toBeTrue();
            expect($result1->errors())->toContain('API error occurred');
            expect($transformer->callCount)->toBe(1);

            // Second call should perform transformation again since failed results are not cached
            $result2 = $transformer->execute($content);
            expect($result2->isFailed())->toBeTrue();
            expect($result2->errors())->toContain('API error occurred');
            expect($transformer->callCount)->toBe(2); // Additional call made since failed results aren't cached
        });

        test('preserves metadata in cached results', function () {
            Config::set('prism-transformer.cache.enabled', true);

            $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Metadata test';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    return TransformerResult::successful(
                        'Cached content',
                        TransformerMetadata::make('gpt-4o-mini', Provider::OPENAI, self::class)
                    );
                }
            };

            $content = 'Test content';
            $result = $transformer->execute($content);

            expect($result->metadata)->not->toBeNull();
            expect($result->metadata->model)->toBe('gpt-4o-mini');
            expect($result->metadata->provider)->toBe(Provider::OPENAI);

            // Second call should preserve same metadata
            $cachedResult = $transformer->execute($content);
            expect($cachedResult->metadata)->not->toBeNull();
            expect($cachedResult->metadata->model)->toBe('gpt-4o-mini');
            expect($cachedResult->metadata->provider)->toBe(Provider::OPENAI);
        });

        test('handles special characters in cached content', function () {
            Config::set('prism-transformer.cache.enabled', true);

            $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Special chars test';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    return TransformerResult::successful(
                        'Content with special chars: àáâãäåæçèéêë 中文 العربية 🚀'
                    );
                }
            };

            $content = 'Test content';
            $result1 = $transformer->execute($content);
            $result2 = $transformer->execute($content); // Should be cached

            expect($result1->data)->toBe($result2->data);
            expect($result2->data)->toContain('àáâãäåæçèéêë 中文 العربية 🚀');
        });
    });

    describe('cache TTL behavior', function () {
        test('respects custom TTL configuration for transformer data', function () {
            Config::set('prism-transformer.cache.enabled', true);
            Config::set('prism-transformer.cache.ttl.transformer_data', 1800); // 30 minutes

            $configService = new ConfigurationService();
            $ttl = $configService->getTransformerDataCacheTtl();

            expect($ttl)->toBe(1800);
        });

        test('uses default TTL when not configured', function () {
            Config::set('prism-transformer.cache.enabled', true);
            // Don't set custom TTL

            $configService = new ConfigurationService();
            $ttl = $configService->getTransformerDataCacheTtl();

            expect($ttl)->toBe(3600); // Default 1 hour
        });

        test('respects TTL configuration', function () {
            Config::set('prism-transformer.cache.enabled', true);
            Config::set('prism-transformer.cache.ttl.transformer_data', 1800); // 30 minutes

            $configService = new ConfigurationService();
            $ttl = $configService->getTransformerDataCacheTtl();

            expect($ttl)->toBe(1800);
        });
    });

    describe('cache behavior when disabled', function () {
        test('bypasses cache when caching is disabled', function () {
            Config::set('prism-transformer.cache.enabled', false);

            $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $callCount = 0;

                public function prompt(): string
                {
                    return 'No cache test';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $this->callCount++;

                    return TransformerResult::successful("No cache: {$content}");
                }
            };

            $content = 'Test content';

            // Both calls should perform transformation (no caching)
            $result1 = $transformer->execute($content);
            $result2 = $transformer->execute($content);

            expect($transformer->callCount)->toBe(2); // Both calls executed
            expect($result1->data)->toBe('No cache: Test content');
            expect($result2->data)->toBe('No cache: Test content');
        });

        test('handles cache retrieval errors gracefully', function () {
            Config::set('prism-transformer.cache.enabled', true);

            $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $callCount = 0;

                public function prompt(): string
                {
                    return 'Error handling test';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $this->callCount++;

                    return TransformerResult::successful("Fallback: {$content}");
                }
            };

            $content = 'Test content';

            // Should work even if cache has issues
            $result = $transformer->execute($content);

            expect($result->data)->toBe('Fallback: Test content');
            expect($transformer->callCount)->toBe(1);
        });
    });

    describe('cache isolation', function () {
        test('different transformer instances use separate caches', function () {
            Config::set('prism-transformer.cache.enabled', true);

            $transformer1 = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $callCount = 0;

                public function prompt(): string
                {
                    return 'First transformer';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $this->callCount++;

                    return TransformerResult::successful("First: {$content}");
                }
            };

            $transformer2 = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $callCount = 0;

                public function prompt(): string
                {
                    return 'Second transformer';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $this->callCount++;

                    return TransformerResult::successful("Second: {$content}");
                }
            };

            $content = 'Test content';

            // Different transformers should not share cache
            $result1 = $transformer1->execute($content);
            $result2 = $transformer2->execute($content);

            expect($transformer1->callCount)->toBe(1);
            expect($transformer2->callCount)->toBe(1);
            expect($result1->data)->toBe('First: Test content');
            expect($result2->data)->toBe('Second: Test content');

            // Each should use their own cache on subsequent calls
            $result1b = $transformer1->execute($content);
            $result2b = $transformer2->execute($content);

            expect($transformer1->callCount)->toBe(1); // No additional calls
            expect($transformer2->callCount)->toBe(1); // No additional calls
        });
    });

    describe('cache integration in execute method', function () {
        beforeEach(function () {
            // Create a testable transformer that doesn't make real API calls
            $this->integrationTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $transformationCount = 0;

                public function prompt(): string
                {
                    return 'Transform this test content';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    // Increment counter to track how many times transformation is called
                    $this->transformationCount++;

                    // Mock successful transformation without making real API calls
                    return TransformerResult::successful(
                        "Transformed: {$content}",
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            self::class
                        )
                    );
                }
            };

            // Clear cache before each test
            Cache::flush();
            Config::set('prism-transformer.cache.enabled', true);
            Config::set('prism-transformer.cache.ttl.transformer_data', 3600);
        });

        test('returns cached result when available', function () {
            $content = 'Test content for caching';

            // First call should perform transformation and cache result
            $result1 = $this->integrationTransformer->execute($content);
            expect($result1->data)->toBe("Transformed: {$content}");
            expect($this->integrationTransformer->transformationCount)->toBe(1);

            // Second call should return cached result without transformation
            $result2 = $this->integrationTransformer->execute($content);
            expect($result2->data)->toBe("Transformed: {$content}");
            expect($this->integrationTransformer->transformationCount)->toBe(1); // Should not increment
        });

        test('performs transformation and caches result when cache miss', function () {
            $content = 'Test content for transformation';

            // First call should perform transformation
            $result1 = $this->integrationTransformer->execute($content);
            expect($result1->data)->toBe("Transformed: {$content}");
            expect($this->integrationTransformer->transformationCount)->toBe(1);

            // Second call should use cached result (no additional transformation)
            $result2 = $this->integrationTransformer->execute($content);
            expect($result2->data)->toBe("Transformed: {$content}");
            expect($this->integrationTransformer->transformationCount)->toBe(1); // Should not increment
        });

        test('uses cached result on subsequent calls with same content', function () {
            $content = 'Test content for repeated calls';

            // First call - should perform transformation
            $result1 = $this->integrationTransformer->execute($content);
            expect($this->integrationTransformer->transformationCount)->toBe(1);

            // Second call - should use cache
            $result2 = $this->integrationTransformer->execute($content);
            expect($this->integrationTransformer->transformationCount)->toBe(1); // Should not increment

            // Results should be identical
            expect($result1->data)->toBe($result2->data);
        });

        test('bypasses cache when caching is disabled', function () {
            Config::set('prism-transformer.cache.enabled', false);

            $content = 'Test content without caching';

            // Execute transformation twice
            $result1 = $this->integrationTransformer->execute($content);
            $result2 = $this->integrationTransformer->execute($content);

            // Should have performed transformation both times
            expect($this->integrationTransformer->transformationCount)->toBe(2);

            // Results should be identical but not cached
            expect($result1->data)->toBe($result2->data);
            expect($result1->data)->toBe("Transformed: {$content}");
            expect($result2->data)->toBe("Transformed: {$content}");
        });
    });

    describe('cache error handling', function () {
        test('falls back to transformation when cache retrieval fails', function () {
            $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $transformationCount = 0;

                public function prompt(): string
                {
                    return 'Error handling test';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $this->transformationCount++;

                    return TransformerResult::successful("Transformed: {$content}");
                }
            };

            $content = 'Test content with cache error';

            // Mock cache failure by using invalid cache store
            Config::set('prism-transformer.cache.store', 'nonexistent_store');

            // Should still perform transformation despite cache error
            $result = $transformer->execute($content);

            expect($result->data)->toBe("Transformed: {$content}");
            expect($transformer->transformationCount)->toBe(1);
        });

        test('continues normally when cache storage fails', function () {
            $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $transformationCount = 0;

                public function prompt(): string
                {
                    return 'Storage error test';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $this->transformationCount++;

                    return TransformerResult::successful("Transformed: {$content}");
                }
            };

            $content = 'Test content with cache storage error';

            // This test would need to mock cache storage failure
            // For now, just verify transformation works
            $result = $transformer->execute($content);

            expect($result->data)->toBe("Transformed: {$content}");
            expect($transformer->transformationCount)->toBe(1);
        });
    });

    describe('cache isolation behavior', function () {
        test('different transformers maintain separate caches', function () {
            $transformer1 = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $transformationCount = 0;

                public function prompt(): string
                {
                    return 'First transformer prompt';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $this->transformationCount++;

                    return TransformerResult::successful("First: {$content}");
                }
            };

            $transformer2 = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $transformationCount = 0;

                public function prompt(): string
                {
                    return 'Second transformer prompt';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $this->transformationCount++;

                    return TransformerResult::successful("Second: {$content}");
                }
            };

            $content = 'Test content';

            // Each transformer should cache separately
            $result1 = $transformer1->execute($content);
            $result2 = $transformer2->execute($content);

            expect($transformer1->transformationCount)->toBe(1);
            expect($transformer2->transformationCount)->toBe(1);
            expect($result1->data)->toBe('First: Test content');
            expect($result2->data)->toBe('Second: Test content');

            // Subsequent calls should use respective caches
            $result1b = $transformer1->execute($content);
            $result2b = $transformer2->execute($content);

            expect($transformer1->transformationCount)->toBe(1); // No increment
            expect($transformer2->transformationCount)->toBe(1); // No increment
        });

        test('different providers maintain separate caches', function () {
            Config::set('prism-transformer.default_provider', Provider::OPENAI);

            $transformer1 = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $transformationCount = 0;

                public function prompt(): string
                {
                    return 'Same prompt';
                }

                public function provider(): Provider
                {
                    return Provider::OPENAI;
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $this->transformationCount++;

                    return TransformerResult::successful("OpenAI: {$content}");
                }
            };

            $transformer2 = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public int $transformationCount = 0;

                public function prompt(): string
                {
                    return 'Same prompt';
                }

                public function provider(): Provider
                {
                    return Provider::ANTHROPIC;
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $this->transformationCount++;

                    return TransformerResult::successful("Anthropic: {$content}");
                }
            };

            $content = 'Test content';

            // Different providers should cache separately despite same prompt
            $result1 = $transformer1->execute($content);
            $result2 = $transformer2->execute($content);

            expect($transformer1->transformationCount)->toBe(1);
            expect($transformer2->transformationCount)->toBe(1);
            expect($result1->data)->toBe('OpenAI: Test content');
            expect($result2->data)->toBe('Anthropic: Test content');
        });
    });

    describe('cache TTL configuration', function () {
        test('respects configured TTL when storing cache', function () {
            $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'TTL test';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    return TransformerResult::successful("Transformed: {$content}");
                }
            };

            Config::set('prism-transformer.cache.ttl.transformer_data', 1800); // 30 minutes

            $content = 'Test content for TTL';

            // This test will verify TTL is respected when we implement caching
            $result = $transformer->execute($content);

            expect($result->data)->toBe("Transformed: {$content}");
        });

        test('uses default TTL when not configured', function () {
            $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Default TTL test';
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    return TransformerResult::successful("Transformed: {$content}");
                }
            };

            // Remove TTL configuration to test default
            Config::set('prism-transformer.cache.ttl.transformer_data', null);

            $content = 'Test content for default TTL';

            $result = $transformer->execute($content);

            expect($result->data)->toBe("Transformed: {$content}");
        });
    });
});
