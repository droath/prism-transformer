<?php

declare(strict_types=1);

use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\Services\ConfigurationService;
use Illuminate\Support\Facades\Config;

describe('ConfigurationService', function () {
    beforeEach(function () {
        $this->configService = new ConfigurationService();
    });

    describe('default provider methods', function () {
        test('can get default provider from configuration', function () {
            Config::set('prism-transformer.default_provider', Provider::ANTHROPIC);

            $defaultProvider = $this->configService->getDefaultProvider();

            expect($defaultProvider)->toBe(Provider::ANTHROPIC);
        });

        test('handles string provider values from configuration', function () {
            Config::set('prism-transformer.default_provider', 'groq');

            $defaultProvider = $this->configService->getDefaultProvider();

            expect($defaultProvider)->toBe(Provider::GROQ);
        });

        test('falls back to OpenAI for invalid provider configuration', function () {
            Config::set('prism-transformer.default_provider', 'invalid_provider');

            $defaultProvider = $this->configService->getDefaultProvider();

            expect($defaultProvider)->toBe(Provider::OPENAI);
        });

        test('falls back to OpenAI when no configuration exists', function () {
            Config::set('prism-transformer.default_provider', null);

            $defaultProvider = $this->configService->getDefaultProvider();

            expect($defaultProvider)->toBe(Provider::OPENAI);
        });
    });

    describe('provider configuration methods', function () {
        test('can get provider configuration', function () {
            $expectedConfig = [
                'default_model' => 'test-model',
                'max_tokens' => 2048,
                'temperature' => 0.5,
            ];

            Config::set('prism-transformer.providers.openai', $expectedConfig);

            $config = $this->configService->getProviderConfig(Provider::OPENAI);

            expect($config)->toBe($expectedConfig);
        });

        test('can get specific provider configuration value', function () {
            Config::set('prism-transformer.providers.anthropic.default_model', 'claude-3-sonnet');

            $model = $this->configService->getProviderConfigValue(Provider::ANTHROPIC, 'default_model');

            expect($model)->toBe('claude-3-sonnet');
        });

        test('returns default for missing provider configuration value', function () {
            $value = $this->configService->getProviderConfigValue(Provider::OPENAI, 'nonexistent_key', 'default_value');

            expect($value)->toBe('default_value');
        });

        test('can get all provider configurations', function () {
            Config::set('prism-transformer.providers', [
                'openai' => ['default_model' => 'gpt-4'],
                'anthropic' => ['default_model' => 'claude-3'],
            ]);

            $allConfigs = $this->configService->getAllProviderConfigs();

            expect($allConfigs)->toHaveKey('openai');
            expect($allConfigs)->toHaveKey('anthropic');
            expect($allConfigs['openai']['default_model'])->toBe('gpt-4');
            expect($allConfigs['anthropic']['default_model'])->toBe('claude-3');
        });
    });

    describe('content fetcher configuration methods', function () {
        test('can get content fetcher configuration', function () {
            $expectedConfig = [
                'timeout' => 60,
                'connect_timeout' => 15,
                'user_agent' => 'Test Agent',
            ];

            Config::set('prism-transformer.content_fetcher', $expectedConfig);

            $config = $this->configService->getContentFetcherConfig();

            expect($config)->toBe($expectedConfig);
        });

        test('can get HTTP timeout', function () {
            Config::set('prism-transformer.content_fetcher.timeout', 45);

            $timeout = $this->configService->getHttpTimeout();

            expect($timeout)->toBe(45);
        });

        test('returns default HTTP timeout when not configured', function () {
            $timeout = $this->configService->getHttpTimeout();

            expect($timeout)->toBe(30);
        });

        test('can get HTTP connect timeout', function () {
            Config::set('prism-transformer.content_fetcher.connect_timeout', 20);

            $connectTimeout = $this->configService->getHttpConnectTimeout();

            expect($connectTimeout)->toBe(20);
        });

        test('can get retry configuration', function () {
            $retryConfig = [
                'max_attempts' => 5,
                'delay' => 2000,
            ];

            Config::set('prism-transformer.content_fetcher.retry', $retryConfig);

            $config = $this->configService->getRetryConfig();

            expect($config)->toBe($retryConfig);
        });

        test('can get validation configuration', function () {
            $validationConfig = [
                'max_content_length' => 5242880,
                'allowed_schemes' => ['https'],
            ];

            Config::set('prism-transformer.content_fetcher.validation', $validationConfig);

            $config = $this->configService->getValidationConfig();

            expect($config)->toBe($validationConfig);
        });
    });

    describe('transformation configuration methods', function () {
        test('can get transformation configuration', function () {
            $transformationConfig = [
                'async_queue' => 'transformations',
                'timeout' => 300,
            ];

            Config::set('prism-transformer.transformation', $transformationConfig);

            $config = $this->configService->getTransformationConfig();

            expect($config)->toBe($transformationConfig);
        });

        test('can get async queue name', function () {
            Config::set('prism-transformer.transformation.async_queue', 'custom_queue');

            $queueName = $this->configService->getAsyncQueue();

            expect($queueName)->toBe('custom_queue');
        });

        test('returns default async queue when not configured', function () {
            $queueName = $this->configService->getAsyncQueue();

            expect($queueName)->toBe('default');
        });
    });

    describe('cache configuration methods', function () {
        test('can get cache configuration', function () {
            $cacheConfig = [
                'enabled' => true,
                'store' => 'redis',
                'prefix' => 'custom_prefix',
            ];

            Config::set('prism-transformer.cache', $cacheConfig);

            $config = $this->configService->getCacheConfig();

            expect($config)->toBe($cacheConfig);
        });

        test('can check if cache is enabled', function () {
            Config::set('prism-transformer.cache.enabled', false);

            $isEnabled = $this->configService->isCacheEnabled();

            expect($isEnabled)->toBeFalse();
        });

        test('returns true for cache enabled by default', function () {
            $isEnabled = $this->configService->isCacheEnabled();

            expect($isEnabled)->toBeTrue();
        });

        test('can get cache store', function () {
            Config::set('prism-transformer.cache.store', 'redis');

            $store = $this->configService->getCacheStore();

            expect($store)->toBe('redis');
        });

        test('can get cache prefix', function () {
            Config::set('prism-transformer.cache.prefix', 'custom_prefix');

            $prefix = $this->configService->getCachePrefix();

            expect($prefix)->toBe('custom_prefix');
        });

        test('can get transformer data cache TTL', function () {
            Config::set('prism-transformer.cache.ttl.transformer_data', 7200);

            $ttl = $this->configService->getTransformerDataCacheTtl();

            expect($ttl)->toBe(7200);
        });

        test('returns default transformer data cache TTL when not configured', function () {
            $ttl = $this->configService->getTransformerDataCacheTtl();

            expect($ttl)->toBe(3600); // 1 hour default
        });

        test('can get content fetch cache TTL', function () {
            Config::set('prism-transformer.cache.ttl.content_fetch', 900);

            $ttl = $this->configService->getContentFetchCacheTtl();

            expect($ttl)->toBe(900);
        });

        test('returns default content fetch cache TTL when not configured', function () {
            $ttl = $this->configService->getContentFetchCacheTtl();

            expect($ttl)->toBe(1800); // 30 minutes default
        });

        test('can get all cache TTL settings', function () {
            $ttlConfig = [
                'content_fetch' => 900,
                'transformer_data' => 7200,
            ];

            Config::set('prism-transformer.cache.ttl', $ttlConfig);

            $config = $this->configService->getCacheTtlConfig();

            expect($config)->toBe($ttlConfig);
        });

        test('returns default TTL config when not configured', function () {
            $config = $this->configService->getCacheTtlConfig();

            expect($config)->toBe([
                'content_fetch' => 1800,
                'transformer_data' => 3600,
            ]);
        });
    });

    describe('client timeout configuration methods', function () {
        test('can get client timeout from configuration', function () {
            Config::set('prism-transformer.transformation.client_options.timeout', 300);

            $timeout = $this->configService->getClientTimeout();

            expect($timeout)->toBe(300);
        });

        test('can get client connect timeout from configuration', function () {
            Config::set('prism-transformer.transformation.client_options.connect_timeout', 20);

            $connectTimeout = $this->configService->getClientConnectTimeout();

            expect($connectTimeout)->toBe(20);
        });

        test('returns default timeout when configuration is missing', function () {
            // Clear the entire transformation config to test fallback
            Config::set('prism-transformer.transformation', []);

            $timeout = $this->configService->getClientTimeout();

            expect($timeout)->toBe(180); // Default value
        });

        test('returns default connect timeout when configuration is missing', function () {
            // Clear the entire transformation config to test fallback
            Config::set('prism-transformer.transformation', []);

            $connectTimeout = $this->configService->getClientConnectTimeout();

            expect($connectTimeout)->toBe(0); // Default value changed to 0
        });

        test('handles string timeout values correctly', function () {
            Config::set('prism-transformer.transformation.client_options.timeout', '450');
            Config::set('prism-transformer.transformation.client_options.connect_timeout', '25');

            $timeout = $this->configService->getClientTimeout();
            $connectTimeout = $this->configService->getClientConnectTimeout();

            expect($timeout)->toBe(450);
            expect($connectTimeout)->toBe(25);
        });

        test('handles zero timeout values', function () {
            Config::set('prism-transformer.transformation.client_options.timeout', 0);
            Config::set('prism-transformer.transformation.client_options.connect_timeout', 0);

            $timeout = $this->configService->getClientTimeout();
            $connectTimeout = $this->configService->getClientConnectTimeout();

            expect($timeout)->toBe(0);
            expect($connectTimeout)->toBe(0);
        });

        test('handles negative timeout values', function () {
            Config::set('prism-transformer.transformation.client_options.timeout', -10);
            Config::set('prism-transformer.transformation.client_options.connect_timeout', -5);

            $timeout = $this->configService->getClientTimeout();
            $connectTimeout = $this->configService->getClientConnectTimeout();

            expect($timeout)->toBe(-10);
            expect($connectTimeout)->toBe(-5);
        });

        test('handles invalid timeout values with type casting', function () {
            Config::set('prism-transformer.transformation.client_options.timeout', 'invalid');
            Config::set('prism-transformer.transformation.client_options.connect_timeout', []);

            $timeout = $this->configService->getClientTimeout();
            $connectTimeout = $this->configService->getClientConnectTimeout();

            // PHP's (int) cast behavior
            expect($timeout)->toBe(0);
            expect($connectTimeout)->toBe(0);
        });

        test('client timeout methods return integers', function () {
            Config::set('prism-transformer.transformation.client_options.timeout', 240.5);
            Config::set('prism-transformer.transformation.client_options.connect_timeout', 15.8);

            $timeout = $this->configService->getClientTimeout();
            $connectTimeout = $this->configService->getClientConnectTimeout();

            expect($timeout)->toBeInt();
            expect($connectTimeout)->toBeInt();
            expect($timeout)->toBe(240);
            expect($connectTimeout)->toBe(15);
        });
    });

    describe('configuration validation', function () {
        test('can validate complete configuration', function () {
            // Set up complete configuration
            Config::set('prism-transformer.default_provider', Provider::OPENAI);
            Config::set('prism-transformer.providers', []);
            Config::set('prism-transformer.content_fetcher', []);
            Config::set('prism-transformer.transformation', []);
            Config::set('prism-transformer.cache', []);

            $missing = $this->configService->validateConfiguration();

            expect($missing)->toBeEmpty();
        });

        test('detects missing configuration sections', function () {
            // Clear all configuration
            Config::set('prism-transformer', []);

            $missing = $this->configService->validateConfiguration();

            expect($missing)->toContain('default_provider');
            expect($missing)->toContain('providers');
            expect($missing)->toContain('content_fetcher');
            expect($missing)->toContain('transformation');
            expect($missing)->toContain('cache');
        });

        test('detects partially missing configuration', function () {
            // Clear all config first
            Config::set('prism-transformer', []);

            // Set only some sections
            Config::set('prism-transformer.default_provider', Provider::OPENAI);
            Config::set('prism-transformer.providers', []);

            $missing = $this->configService->validateConfiguration();

            expect($missing)->toContain('content_fetcher');
            expect($missing)->toContain('transformation');
            expect($missing)->toContain('cache');
            expect($missing)->not->toContain('default_provider');
            expect($missing)->not->toContain('providers');
        });
    });

    describe('service container integration', function () {
        test('can be resolved from service container', function () {
            $service = app(ConfigurationService::class);

            expect($service)->toBeInstanceOf(ConfigurationService::class);
        });

        test('returns same instance when resolved multiple times', function () {
            $service1 = app(ConfigurationService::class);
            $service2 = app(ConfigurationService::class);

            expect($service1)->toBe($service2);
        });
    });
});
