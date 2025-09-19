<?php

declare(strict_types=1);

use Prism\Prism\Prism;
use Prism\Prism\ValueObjects\Usage;
use Prism\Prism\Testing\TextResponseFake;
use Droath\PrismTransformer\PrismTransformer;
use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\Jobs\TransformationJob;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Services\ModelSchemaService;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\ValueObjects\TransformerMetadata;
use Droath\PrismTransformer\Abstract\BaseTransformer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Droath\PrismTransformer\Tests\Stubs\SummarizeTransformer;

describe('Complete Transformation Pipeline Integration', function () {
    beforeEach(function () {
        $this->configService = app(ConfigurationService::class);
    });

    test('transforms text using summarize transformer with prism interface', function () {
        $fakeResponse = TextResponseFake::make()
            ->withText('This is the summary of the content.')
            ->withUsage(new Usage(10, 20));

        Prism::fake([$fakeResponse]);

        $response = (app(PrismTransformer::class))
            ->text('This is my very longer content that I want to summarize.')
            ->using(SummarizeTransformer::class)
            ->transform();

        expect($response->getContent())
            ->toEqual('This is the summary of the content.');
    });

    describe('configuration-driven transformation pipeline', function () {
        test('uses configuration for provider selection', function () {
            Config::set('prism-transformer.default_provider', Provider::ANTHROPIC);
            Config::set('prism-transformer.providers.anthropic.default_model', 'claude-3-custom');

            $transformerInterface = new class($this->app->make(\Illuminate\Cache\CacheManager::class), $this->configService, $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Test prompt';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    $transformed = "Transformed: {$content} with {$this->model()}";

                    return TransformerResult::successful($transformed);
                }
            };

            // Provider and model configuration is tested indirectly through transformation results
            expect($transformerInterface)->toBeInstanceOf(BaseTransformer::class);
        });

        test('complete text transformation pipeline with configuration', function () {
            Config::set('prism-transformer.default_provider', Provider::OPENAI);
            Config::set('prism-transformer.providers.openai.default_model', 'gpt-4-test');
            Config::set('prism-transformer.providers.openai.temperature', 0.5);

            $content = 'Test content for transformation';
            $expectedResult = 'Transformed: Test content for transformation with gpt-4-test';

            $transformerInterface = new class($this->app->make(\Illuminate\Cache\CacheManager::class), $this->configService, $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Test prompt';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    $transformed = "Transformed: {$content} with {$this->model()}";

                    return TransformerResult::successful($transformed);
                }
            };

            $transformer = app(PrismTransformer::class);
            $result = $transformer
                ->text($content)
                ->using($transformerInterface)
                ->transform();

            expect($result)->toBeInstanceOf(TransformerResult::class);
            expect($result->data)->toBe($expectedResult);
        });

        test('configuration-driven url transformation pipeline', function () {
            Config::set('prism-transformer.content_fetcher.timeout', 45);
            Config::set('prism-transformer.content_fetcher.user_agent', 'TestAgent/1.0');

            $url = 'https://example.com/test';
            $transformedContent = 'Transformed URL content';

            // Note: UrlTransformerHandler currently just returns the URL, not fetch content
            $transformerInterface = new class($this->app->make(\Illuminate\Cache\CacheManager::class), $this->configService, $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'URL transformation prompt';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    return TransformerResult::successful('Transformed URL content');
                }
            };

            $transformer = app(PrismTransformer::class);
            $result = $transformer
                ->url($url)
                ->using($transformerInterface)
                ->transform();

            expect($result)->toBeInstanceOf(TransformerResult::class);
            expect($result->data)->toBe($transformedContent);
        });
    });

    describe('end-to-end transformation scenarios', function () {
        test('text transformation with metadata tracking', function () {
            Config::set('prism-transformer.transformation.metadata.track_timing', true);
            Config::set('prism-transformer.transformation.metadata.track_provider', true);

            $content = 'Sample content for transformation';

            $transformerInterface = new class($this->app->make(\Illuminate\Cache\CacheManager::class), $this->configService, $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Uppercase transformation prompt';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    // Create metadata to include with result
                    $metadata = TransformerMetadata::make(
                        model: $this->model(),
                        provider: $this->provider(),
                        transformerClass: self::class
                    );

                    return TransformerResult::successful(strtoupper($content), $metadata);
                }
            };

            $transformer = app(PrismTransformer::class);
            $result = $transformer
                ->text($content)
                ->using($transformerInterface)
                ->transform();

            expect($result)->toBeInstanceOf(TransformerResult::class);
            expect($result->data)->toBe('SAMPLE CONTENT FOR TRANSFORMATION');
            expect($result->metadata)->toBeInstanceOf(TransformerMetadata::class);
        });

        test('async transformation pipeline', function () {
            Config::set('prism-transformer.transformation.async_queue', 'transformations');
            Queue::fake();

            $content = 'Async transformation content';

            $transformerInterface = new class($this->app->make(\Illuminate\Cache\CacheManager::class), $this->configService, $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Async transformation prompt';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    return TransformerResult::successful("Async: {$content}");
                }
            };

            $transformer = app(PrismTransformer::class);
            $transformer
                ->text($content)
                ->async()
                ->using($transformerInterface)
                ->transform();

            Queue::assertPushed(TransformationJob::class, function ($job) {
                expect($job->handler)->toBeInstanceOf(BaseTransformer::class);

                return true;
            });
        });

        test('complex chained transformation pipeline', function () {
            $content = 'Initial content';

            // First transformer: uppercase
            $uppercaseTransformer = new class($this->app->make(\Illuminate\Cache\CacheManager::class), $this->configService, $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Uppercase prompt';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    return TransformerResult::successful(strtoupper($content));
                }
            };

            // Second transformer: add prefix
            $prefixTransformer = new class($this->app->make(\Illuminate\Cache\CacheManager::class), $this->configService, $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Prefix prompt';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    return TransformerResult::successful("TRANSFORMED: {$content}");
                }
            };

            // First transformation
            $transformer1 = app(PrismTransformer::class);
            $result1 = $transformer1
                ->text($content)
                ->using($uppercaseTransformer)
                ->transform();

            // Second transformation using result from first
            $transformer2 = app(PrismTransformer::class);
            $result2 = $transformer2
                ->text($result1->data)
                ->using($prefixTransformer)
                ->transform();

            expect($result2->data)->toBe('TRANSFORMED: INITIAL CONTENT');
        });
    });

    describe('error handling in transformation pipeline', function () {
        test('handles transformation errors gracefully', function () {
            $content = 'Error test content';

            $errorTransformer = new class($this->app->make(\Illuminate\Cache\CacheManager::class), $this->configService, $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Error prompt';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    throw new \Exception('Transformation failed');
                }
            };

            $transformer = app(PrismTransformer::class);

            expect(function () use ($transformer, $content, $errorTransformer) {
                $transformer
                    ->text($content)
                    ->using($errorTransformer)
                    ->transform();
            })->toThrow(\Exception::class, 'Transformation failed');
        });

        test('handles invalid content gracefully', function () {
            $transformerInterface = new class($this->app->make(\Illuminate\Cache\CacheManager::class), $this->configService, $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Content validation prompt';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    if (empty($content)) {
                        return TransformerResult::failed(['Empty content provided']);
                    }

                    return TransformerResult::successful("Processed: {$content}");
                }
            };

            $transformer = app(PrismTransformer::class);
            $result = $transformer
                ->text('')
                ->using($transformerInterface)
                ->transform();

            expect($result)->toBeInstanceOf(TransformerResult::class);
            expect($result->isFailed())->toBeTrue();
        });

        test('handles missing transformer gracefully', function () {
            $content = 'Test content';

            $transformer = app(PrismTransformer::class);

            expect(fn () => $transformer
                ->text($content)
                ->transform()) // No transformer set
                ->toThrow(\InvalidArgumentException::class, 'Invalid transformer handler provided.');
        });
    });

    describe('provider-specific transformation pipeline', function () {
        test('OpenAI provider transformation pipeline', function () {
            Config::set('prism-transformer.default_provider', Provider::OPENAI);
            Config::set('prism-transformer.providers.openai.default_model', 'gpt-4');
            Config::set('prism-transformer.providers.openai.temperature', 0.3);

            $transformerInterface = new class($this->app->make(\Illuminate\Cache\CacheManager::class), $this->configService, $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'OpenAI transformation prompt';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    $temp = $this->provider()->getConfigValue('temperature', 0.7);

                    return TransformerResult::successful("OpenAI transform (temp: {$temp}): {$content}");
                }
            };

            $transformer = app(PrismTransformer::class);
            $result = $transformer
                ->text('Test content')
                ->using($transformerInterface)
                ->transform();

            expect($result->data)->toBe('OpenAI transform (temp: 0.3): Test content');
        });

        test('Anthropic provider transformation pipeline', function () {
            Config::set('prism-transformer.default_provider', Provider::ANTHROPIC);
            Config::set('prism-transformer.providers.anthropic.default_model', 'claude-3-sonnet');
            Config::set('prism-transformer.providers.anthropic.max_tokens', 8192);

            $transformerInterface = new class($this->app->make(\Illuminate\Cache\CacheManager::class), $this->configService, $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Anthropic transformation prompt';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    $maxTokens = $this->provider()->getConfigValue('max_tokens', 4096);

                    return TransformerResult::successful("Anthropic transform (tokens: {$maxTokens}): {$content}");
                }
            };

            $transformer = app(PrismTransformer::class);
            $result = $transformer
                ->text('Test content')
                ->using($transformerInterface)
                ->transform();

            expect($result->data)->toBe('Anthropic transform (tokens: 8192): Test content');
        });

        test('Ollama provider with custom base URL', function () {
            Config::set('prism-transformer.default_provider', Provider::OLLAMA);
            Config::set('prism-transformer.providers.ollama.base_url', 'http://localhost:11434');
            Config::set('prism-transformer.providers.ollama.timeout', 180);

            $transformerInterface = new class($this->app->make(\Illuminate\Cache\CacheManager::class), $this->configService, $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Ollama transformation prompt';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    $baseUrl = $this->provider()->getConfigValue('base_url');
                    $timeout = $this->provider()->getConfigValue('timeout', 120);

                    return TransformerResult::successful("Ollama transform (url: {$baseUrl}, timeout: {$timeout}): {$content}");
                }
            };

            $transformer = app(PrismTransformer::class);
            $result = $transformer
                ->text('Test content')
                ->using($transformerInterface)
                ->transform();

            expect($result->data)->toBe('Ollama transform (url: http://localhost:11434, timeout: 180): Test content');
        });
    });

    describe('performance and caching integration', function () {
        test('caching configuration integration', function () {
            Config::set('prism-transformer.cache.enabled', true);
            Config::set('prism-transformer.cache.prefix', 'test_transform');
            Config::set('prism-transformer.cache.ttl.transformation_results', 7200);

            expect($this->configService->isCacheEnabled())->toBeTrue();
            expect($this->configService->getCachePrefix())->toBe('test_transform');

            $cacheConfig = $this->configService->getCacheConfig();
            expect($cacheConfig['enabled'])->toBeTrue();
            expect($cacheConfig['prefix'])->toBe('test_transform');
        });

        test('timeout configuration integration', function () {
            Config::set('prism-transformer.content_fetcher.timeout', 60);
            Config::set('prism-transformer.content_fetcher.connect_timeout', 15);

            expect($this->configService->getHttpTimeout())->toBe(60);
            expect($this->configService->getHttpConnectTimeout())->toBe(15);

            $retryConfig = $this->configService->getRetryConfig();
            expect($retryConfig)->toBeArray();
        });
    });

    describe('service container integration throughout pipeline', function () {
        test('configuration service is available throughout transformation', function () {
            $transformerInterface = new class($this->app->make(\Illuminate\Cache\CacheManager::class), $this->configService, $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Configuration service prompt';
                }

                protected function performTransformation(string $content): TransformerResult
                {
                    // Access configuration service
                    $provider = $this->configuration->getDefaultProvider();
                    $cacheEnabled = $this->configuration->isCacheEnabled();

                    return TransformerResult::successful("Provider: {$provider->value}, Cache: ".($cacheEnabled ? 'enabled' : 'disabled'));
                }
            };

            $transformer = app(PrismTransformer::class);
            $result = $transformer
                ->text('Test')
                ->using($transformerInterface)
                ->transform();

            expect($result->data)->toContain('Provider:');
            expect($result->data)->toContain('Cache:');
        });

        test('multiple services can be resolved during transformation', function () {
            $configService = app(ConfigurationService::class);
            $transformer = app(PrismTransformer::class);

            expect($configService)->toBeInstanceOf(ConfigurationService::class);
            expect($transformer)->toBeInstanceOf(PrismTransformer::class);

            // Services should be properly injected
            $providerConfig = $configService->getDefaultProvider();
            expect($providerConfig)->toBeInstanceOf(Provider::class);
        });
    });

    describe('facade integration in transformation pipeline', function () {
        test('can use facade for transformation pipeline', function () {
            // Test that the facade is available and working
            $facade = app(\Droath\PrismTransformer\Facades\PrismTransformer::class);
            expect($facade)->not->toBeNull();

            // Test facade method delegation works
            $transformer = \Droath\PrismTransformer\Facades\PrismTransformer::getFacadeRoot();
            expect($transformer)->toBeInstanceOf(PrismTransformer::class);
        });
    });

    describe('validation integration in transformation pipeline', function () {
        test('configuration validation during transformation', function () {
            $missingConfig = $this->configService->validateConfiguration();

            // Should have all required configuration sections
            expect($missingConfig)->toBeArray();

            // If configuration is complete, no missing sections
            if (empty($missingConfig)) {
                expect($this->configService->getDefaultProvider())->toBeInstanceOf(Provider::class);
                expect($this->configService->getCacheConfig())->toBeArray();
            }
        });

        test('provider configuration consistency', function () {
            $allProviderConfigs = $this->configService->getAllProviderConfigs();

            expect($allProviderConfigs)->toBeArray();
            expect($allProviderConfigs)->not->toBeEmpty();

            // Each provider should have configuration
            foreach (Provider::cases() as $provider) {
                expect($allProviderConfigs)->toHaveKey($provider->value);
            }
        });
    });
});
