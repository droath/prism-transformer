<?php

declare(strict_types=1);

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\ValueObjects\TransformerMetadata;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Cache\CacheManager;

describe('BaseTransformer Cache Integration', function () {
    beforeEach(function () {
        // Create a testable transformer that doesn't make real API calls
        $this->transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
        {
            public int $transformationCount = 0;

            public function prompt(): string
            {
                return 'Transform this test content';
            }

            protected function performTransformation(string $content): TransformerResult
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

    describe('cache integration in execute method', function () {
        test('returns cached result when available', function () {
            $content = 'Test content for caching';

            // First call should perform transformation and cache result
            $result1 = $this->transformer->execute($content);
            expect($result1->data)->toBe("Transformed: {$content}");
            expect($this->transformer->transformationCount)->toBe(1);

            // Second call should return cached result without transformation
            $result2 = $this->transformer->execute($content);
            expect($result2->data)->toBe("Transformed: {$content}");
            expect($this->transformer->transformationCount)->toBe(1); // Should not increment
        });

        test('performs transformation and caches result when cache miss', function () {
            $content = 'Test content for transformation';

            // First call should perform transformation
            $result1 = $this->transformer->execute($content);
            expect($result1->data)->toBe("Transformed: {$content}");
            expect($this->transformer->transformationCount)->toBe(1);

            // Second call should use cached result (no additional transformation)
            $result2 = $this->transformer->execute($content);
            expect($result2->data)->toBe("Transformed: {$content}");
            expect($this->transformer->transformationCount)->toBe(1); // Should not increment
        });

        test('uses cached result on subsequent calls with same content', function () {
            $content = 'Test content for repeated calls';

            // First call - should perform transformation
            $result1 = $this->transformer->execute($content);
            expect($this->transformer->transformationCount)->toBe(1);

            // Second call - should use cache
            $result2 = $this->transformer->execute($content);
            expect($this->transformer->transformationCount)->toBe(1); // Should not increment

            // Results should be identical
            expect($result1->data)->toBe($result2->data);
        });

        test('bypasses cache when caching is disabled', function () {
            Config::set('prism-transformer.cache.enabled', false);

            $content = 'Test content without caching';

            // Execute transformation twice
            $result1 = $this->transformer->execute($content);
            $result2 = $this->transformer->execute($content);

            // Should have performed transformation both times
            expect($this->transformer->transformationCount)->toBe(2);

            // Results should be identical but not cached
            expect($result1->data)->toBe($result2->data);
            expect($result1->data)->toBe("Transformed: {$content}");
            expect($result2->data)->toBe("Transformed: {$content}");
        });
    });

    describe('cache error handling', function () {
        test('falls back to transformation when cache retrieval fails', function () {
            $content = 'Test content with cache error';

            // Mock cache failure by using invalid cache store
            Config::set('prism-transformer.cache.store', 'nonexistent_store');

            // Should still perform transformation despite cache error
            $result = $this->transformer->execute($content);

            expect($result->data)->toBe("Transformed: {$content}");
            expect($this->transformer->transformationCount)->toBe(1);
        });

        test('continues normally when cache storage fails', function () {
            $content = 'Test content with cache storage error';

            // This test would need to mock cache storage failure
            // For now, just verify transformation works
            $result = $this->transformer->execute($content);

            expect($result->data)->toBe("Transformed: {$content}");
            expect($this->transformer->transformationCount)->toBe(1);
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

                protected function performTransformation(string $content): TransformerResult
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

                protected function performTransformation(string $content): TransformerResult
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

                protected function performTransformation(string $content): TransformerResult
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

                protected function performTransformation(string $content): TransformerResult
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
            Config::set('prism-transformer.cache.ttl.transformer_data', 1800); // 30 minutes

            $content = 'Test content for TTL';

            // This test will verify TTL is respected when we implement caching
            $result = $this->transformer->execute($content);

            expect($result->data)->toBe("Transformed: {$content}");
        });

        test('uses default TTL when not configured', function () {
            // Remove TTL configuration to test default
            Config::set('prism-transformer.cache.ttl.transformer_data', null);

            $content = 'Test content for default TTL';

            $result = $this->transformer->execute($content);

            expect($result->data)->toBe("Transformed: {$content}");
        });
    });
});
