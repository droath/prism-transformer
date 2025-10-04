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

    describe('content fetcher configuration methods', function () {
        test('can get HTTP timeout', function () {
            Config::set('prism-transformer.content_fetcher.timeout', 45);

            $timeout = $this->configService->getHttpTimeout();

            expect($timeout)->toBe(45);
        });

        test('returns default HTTP timeout when not configured', function () {
            $timeout = $this->configService->getHttpTimeout();

            expect($timeout)->toBe(30);
        });
    });

    describe('transformation configuration methods', function () {
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
        test('can check if content fetch cache is enabled', function () {
            Config::set('prism-transformer.cache.content_fetch.enabled', true);

            $isEnabled = $this->configService->isContentFetchCacheEnabled();

            expect($isEnabled)->toBeTrue();
        });

        test('returns false for content fetch cache enabled by default', function () {
            $isEnabled = $this->configService->isContentFetchCacheEnabled();

            expect($isEnabled)->toBeFalse();
        });

        test('can check if transformer results cache is enabled', function () {
            Config::set('prism-transformer.cache.transformer_results.enabled', true);

            $isEnabled = $this->configService->isTransformerResultsCacheEnabled();

            expect($isEnabled)->toBeTrue();
        });

        test('returns false for transformer results cache enabled by default', function () {
            $isEnabled = $this->configService->isTransformerResultsCacheEnabled();

            expect($isEnabled)->toBeFalse();
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

        test('can get transformer results cache TTL', function () {
            Config::set('prism-transformer.cache.transformer_results.ttl', 7200);

            $ttl = $this->configService->getTransformerResultsCacheTtl();

            expect($ttl)->toBe(7200);
        });

        test('returns default transformer results cache TTL when not configured', function () {
            $ttl = $this->configService->getTransformerResultsCacheTtl();

            expect($ttl)->toBe(3600); // 1 hour default
        });

        test('can get content fetch cache TTL', function () {
            Config::set('prism-transformer.cache.content_fetch.ttl', 900);

            $ttl = $this->configService->getContentFetchCacheTtl();

            expect($ttl)->toBe(900);
        });

        test('returns default content fetch cache TTL when not configured', function () {
            $ttl = $this->configService->getContentFetchCacheTtl();

            expect($ttl)->toBe(1800); // 30 minutes default
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
