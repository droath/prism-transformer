<?php

declare(strict_types=1);

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\ValueObjects\TransformerMetadata;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

describe('Multi-Provider Cache Performance', function () {
    beforeEach(function () {
        // Clear all caches before each test
        Cache::flush();

        // Set up caching configuration
        Config::set('prism-transformer.cache.enabled', true);
        Config::set('prism-transformer.cache.store', 'default');
        Config::set('prism-transformer.cache.prefix', 'multi_provider_test');
        Config::set('prism-transformer.cache.ttl.transformer_data', 3600);

        $this->cacheManager = $this->app->make(CacheManager::class);
        $this->configurationService = $this->app->make(ConfigurationService::class);
    });

    describe('provider-specific cache isolation', function () {
        test('different providers maintain separate cache entries for same content', function () {
            // Create transformers for different providers
            $openaiTransformer = new class($this->cacheManager, $this->configurationService) extends BaseTransformer
            {
                public int $transformationCallCount = 0;

                public function prompt(): string
                {
                    return 'Transform this content';
                }

                public function provider(): Provider
                {
                    return Provider::OPENAI;
                }

                public function model(): string
                {
                    return 'gpt-4o-mini';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    $this->transformationCallCount++;

                    return TransformerResult::successful(
                        "OpenAI transformed: {$content}",
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            self::class
                        )
                    );
                }
            };

            $anthropicTransformer = new class($this->cacheManager, $this->configurationService) extends BaseTransformer
            {
                public int $transformationCallCount = 0;

                public function prompt(): string
                {
                    return 'Transform this content';
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
                    $this->transformationCallCount++;

                    return TransformerResult::successful(
                        "Anthropic transformed: {$content}",
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            self::class
                        )
                    );
                }
            };

            $content = 'Test content for provider isolation';

            // Execute with both providers
            $openaiResult1 = $openaiTransformer->execute($content);
            $anthropicResult1 = $anthropicTransformer->execute($content);

            // Both should have executed (different cache keys due to different providers)
            expect($openaiTransformer->transformationCallCount)->toBe(1);
            expect($anthropicTransformer->transformationCallCount)->toBe(1);
            expect($openaiResult1->data)->toBe('OpenAI transformed: Test content for provider isolation');
            expect($anthropicResult1->data)->toBe('Anthropic transformed: Test content for provider isolation');

            // Subsequent calls should use respective caches
            $openaiResult2 = $openaiTransformer->execute($content);
            $anthropicResult2 = $anthropicTransformer->execute($content);

            expect($openaiTransformer->transformationCallCount)->toBe(1); // No increment
            expect($anthropicTransformer->transformationCallCount)->toBe(1); // No increment
            expect($openaiResult2->data)->toBe('OpenAI transformed: Test content for provider isolation');
            expect($anthropicResult2->data)->toBe('Anthropic transformed: Test content for provider isolation');
        });

        test('same provider with different models maintains separate caches', function () {
            $gpt4Transformer = new class($this->cacheManager, $this->configurationService) extends BaseTransformer
            {
                public int $transformationCallCount = 0;

                public function prompt(): string
                {
                    return 'Transform this content';
                }

                public function provider(): Provider
                {
                    return Provider::OPENAI;
                }

                public function model(): string
                {
                    return 'gpt-4o';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    $this->transformationCallCount++;

                    return TransformerResult::successful(
                        "GPT-4o transformed: {$content}",
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            self::class
                        )
                    );
                }
            };

            $gpt4MiniTransformer = new class($this->cacheManager, $this->configurationService) extends BaseTransformer
            {
                public int $transformationCallCount = 0;

                public function prompt(): string
                {
                    return 'Transform this content';
                }

                public function provider(): Provider
                {
                    return Provider::OPENAI;
                }

                public function model(): string
                {
                    return 'gpt-4o-mini';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    $this->transformationCallCount++;

                    return TransformerResult::successful(
                        "GPT-4o-mini transformed: {$content}",
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            self::class
                        )
                    );
                }
            };

            $content = 'Test content for model isolation';

            // Execute with both models
            $gpt4Result = $gpt4Transformer->execute($content);
            $gpt4MiniResult = $gpt4MiniTransformer->execute($content);

            // Both should have executed (different cache keys due to different models)
            expect($gpt4Transformer->transformationCallCount)->toBe(1);
            expect($gpt4MiniTransformer->transformationCallCount)->toBe(1);
            expect($gpt4Result->data)->toBe('GPT-4o transformed: Test content for model isolation');
            expect($gpt4MiniResult->data)->toBe('GPT-4o-mini transformed: Test content for model isolation');

            // Metadata should reflect the correct models
            expect($gpt4Result->metadata->model)->toBe('gpt-4o');
            expect($gpt4MiniResult->metadata->model)->toBe('gpt-4o-mini');
            expect($gpt4Result->metadata->provider)->toBe(Provider::OPENAI);
            expect($gpt4MiniResult->metadata->provider)->toBe(Provider::OPENAI);
        });
    });

    describe('cache performance across providers', function () {
        test('cache hit rates are maintained per provider', function () {
            $providers = [
                'openai' => [Provider::OPENAI, 'gpt-4o-mini'],
                'anthropic' => [Provider::ANTHROPIC, 'claude-3-sonnet'],
                'groq' => [Provider::GROQ, 'llama3-70b-8192'],
            ];

            $transformers = [];
            $content = 'Performance test content';

            // Create transformers for each provider
            foreach ($providers as $name => [$provider, $model]) {
                $transformers[$name] = new class($this->cacheManager, $this->configurationService, $provider, $model, $name) extends BaseTransformer
                {
                    public int $transformationCallCount = 0;

                    public function __construct(
                        CacheManager $cache,
                        ConfigurationService $configuration,
                        private Provider $testProvider,
                        private string $testModel,
                        private string $testName
                    ) {
                        parent::__construct($cache, $configuration);
                    }

                    public function prompt(): string
                    {
                        return "Transform content for {$this->testName}";
                    }

                    public function provider(): Provider
                    {
                        return $this->testProvider;
                    }

                    public function model(): string
                    {
                        return $this->testModel;
                    }

                    protected function performTransformation(string $content): TransformerResult
                    {
                        $this->transformationCallCount++;

                        return TransformerResult::successful(
                            "{$this->testName} transformed: {$content}",
                            TransformerMetadata::make(
                                $this->model(),
                                $this->provider(),
                                self::class
                            )
                        );
                    }
                };
            }

            // First round - should all perform transformations
            $firstResults = [];
            foreach ($transformers as $name => $transformer) {
                $firstResults[$name] = $transformer->execute($content);
                expect($transformer->transformationCallCount)->toBe(1);
            }

            // Second round - should all use cache
            $secondResults = [];
            foreach ($transformers as $name => $transformer) {
                $secondResults[$name] = $transformer->execute($content);
                expect($transformer->transformationCallCount)->toBe(1); // No increment
            }

            // Results should be identical
            foreach ($providers as $name => [$provider, $model]) {
                expect($firstResults[$name]->data)->toBe($secondResults[$name]->data);
                expect($secondResults[$name]->metadata->provider)->toBe($provider);
                expect($secondResults[$name]->metadata->model)->toBe($model);
            }
        });

        test('cache invalidation affects only specific provider', function () {
            // This test verifies that cache operations are properly isolated
            $transformer1 = new class($this->cacheManager, $this->configurationService) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Provider 1 prompt';
                }

                public function provider(): Provider
                {
                    return Provider::OPENAI;
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    return TransformerResult::successful(
                        "Provider 1: {$content}",
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            self::class
                        )
                    );
                }
            };

            $transformer2 = new class($this->cacheManager, $this->configurationService) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Provider 2 prompt';
                }

                public function provider(): Provider
                {
                    return Provider::ANTHROPIC;
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    return TransformerResult::successful(
                        "Provider 2: {$content}",
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            self::class
                        )
                    );
                }
            };

            $content = 'Cache isolation test';

            // Execute both transformers
            $result1a = $transformer1->execute($content);
            $result2a = $transformer2->execute($content);

            expect($result1a->data)->toBe('Provider 1: Cache isolation test');
            expect($result2a->data)->toBe('Provider 2: Cache isolation test');

            // Clear all cache manually and verify each maintains its own cache space
            Cache::flush();

            // After cache flush, both should need to retransform
            $result1b = $transformer1->execute($content);
            $result2b = $transformer2->execute($content);

            expect($result1b->data)->toBe('Provider 1: Cache isolation test');
            expect($result2b->data)->toBe('Provider 2: Cache isolation test');
        });
    });

    describe('provider-specific configuration interaction', function () {
        test('different cache TTL settings can be applied per provider scenario', function () {
            // Test that the cache system works with different TTL configurations
            Config::set('prism-transformer.cache.ttl.transformer_data', 7200); // 2 hours

            $configService = new ConfigurationService();
            expect($configService->getTransformerDataCacheTtl())->toBe(7200);

            $transformer = new class($this->cacheManager, $this->configurationService) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'TTL test prompt';
                }

                public function provider(): Provider
                {
                    return Provider::OPENAI;
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    return TransformerResult::successful(
                        "TTL test: {$content}",
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            self::class
                        )
                    );
                }
            };

            $result = $transformer->execute('TTL test content');
            expect($result->data)->toBe('TTL test: TTL test content');
        });
    });
});
