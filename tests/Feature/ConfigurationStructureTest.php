<?php

declare(strict_types=1);

use Droath\PrismTransformer\Enums\Provider;
use Illuminate\Support\Facades\Config;

describe('Configuration Structure', function () {
    describe('default provider configuration', function () {
        test('has default provider setting', function () {
            $defaultProvider = config('prism-transformer.default_provider');
            expect($defaultProvider)->toBeInstanceOf(Provider::class);
            expect($defaultProvider)->toBe(Provider::OPENAI);
        });

        test('can override default provider via config', function () {
            Config::set('prism-transformer.default_provider', Provider::ANTHROPIC);

            expect(config('prism-transformer.default_provider'))->toBe(Provider::ANTHROPIC);
        });
    });

    describe('provider settings configuration', function () {
        test('has providers configuration section', function () {
            $providers = config('prism-transformer.providers');
            expect($providers)->toBeArray();
            expect($providers)->not->toBeEmpty();
        });

        test('includes all supported providers', function () {
            $providers = config('prism-transformer.providers');
            $expectedProviders = [
                'openai', 'anthropic', 'groq', 'ollama', 'gemini',
                'mistral', 'deepseek', 'xai', 'openrouter', 'voyageai', 'elevenlabs',
            ];

            foreach ($expectedProviders as $provider) {
                expect($providers)->toHaveKey($provider);
            }
        });

        test('each provider has default model configuration', function () {
            $providers = config('prism-transformer.providers');

            foreach ($providers as $providerKey => $providerConfig) {
                expect($providerConfig)->toHaveKey('default_model');
                expect($providerConfig['default_model'])->toBeString();
                expect($providerConfig['default_model'])->not->toBeEmpty();
            }
        });

        test('OpenAI provider has correct default settings', function () {
            $openaiConfig = config('prism-transformer.providers.openai');

            expect($openaiConfig)->toHaveKey('default_model');
            expect($openaiConfig)->toHaveKey('max_tokens');
            expect($openaiConfig)->toHaveKey('temperature');

            expect($openaiConfig['default_model'])->toBe('gpt-4o-mini');
            expect($openaiConfig['max_tokens'])->toBe(4096);
            expect($openaiConfig['temperature'])->toBe(0.7);
        });

        test('Anthropic provider has correct default settings', function () {
            $anthropicConfig = config('prism-transformer.providers.anthropic');

            expect($anthropicConfig['default_model'])->toBe('claude-3-5-haiku-20241022');
            expect($anthropicConfig['max_tokens'])->toBe(4096);
            expect($anthropicConfig['temperature'])->toBe(0.7);
        });

        test('Ollama provider has unique settings', function () {
            $ollamaConfig = config('prism-transformer.providers.ollama');

            expect($ollamaConfig)->toHaveKey('base_url');
            expect($ollamaConfig)->toHaveKey('timeout');
            expect($ollamaConfig['base_url'])->toBe('http://localhost:11434');
            expect($ollamaConfig['timeout'])->toBe(120);
        });

        test('ElevenLabs provider has voice settings', function () {
            $elevenlabsConfig = config('prism-transformer.providers.elevenlabs');

            expect($elevenlabsConfig)->toHaveKey('voice_settings');
            expect($elevenlabsConfig['voice_settings'])->toHaveKey('stability');
            expect($elevenlabsConfig['voice_settings'])->toHaveKey('similarity_boost');
        });
    });

    describe('content fetcher configuration', function () {
        test('has content fetcher configuration section', function () {
            $contentFetcher = config('prism-transformer.content_fetcher');
            expect($contentFetcher)->toBeArray();
            expect($contentFetcher)->not->toBeEmpty();
        });

        test('has timeout settings', function () {
            $contentFetcher = config('prism-transformer.content_fetcher');

            expect($contentFetcher)->toHaveKey('timeout');
            expect($contentFetcher)->toHaveKey('connect_timeout');
            expect($contentFetcher['timeout'])->toBe(30);
            expect($contentFetcher['connect_timeout'])->toBe(10);
        });

        test('has retry configuration', function () {
            $retryConfig = config('prism-transformer.content_fetcher.retry');

            expect($retryConfig)->toBeArray();
            expect($retryConfig)->toHaveKey('max_attempts');
            expect($retryConfig)->toHaveKey('delay');
            expect($retryConfig['max_attempts'])->toBe(3);
            expect($retryConfig['delay'])->toBe(1000);
        });

        test('has validation settings', function () {
            $validationConfig = config('prism-transformer.content_fetcher.validation');

            expect($validationConfig)->toBeArray();
            expect($validationConfig)->toHaveKey('max_content_length');
            expect($validationConfig)->toHaveKey('allowed_schemes');
            expect($validationConfig)->toHaveKey('allow_localhost');

            expect($validationConfig['allowed_schemes'])->toContain('http');
            expect($validationConfig['allowed_schemes'])->toContain('https');
        });
    });

    describe('transformation configuration', function () {
        test('has transformation configuration section', function () {
            $transformation = config('prism-transformer.transformation');
            expect($transformation)->toBeArray();
        });

        test('has async queue setting', function () {
            $asyncQueue = config('prism-transformer.transformation.async_queue');
            expect($asyncQueue)->toBe('default');
        });
    });

    describe('cache configuration', function () {
        test('has cache configuration section', function () {
            $cache = config('prism-transformer.cache');
            expect($cache)->toBeArray();
            expect($cache)->not->toBeEmpty();
        });

        test('has cache enabled setting', function () {
            $cacheEnabled = config('prism-transformer.cache.enabled');
            expect($cacheEnabled)->toBeTrue();
        });

        test('has cache store and prefix settings', function () {
            $cache = config('prism-transformer.cache');

            expect($cache)->toHaveKey('store');
            expect($cache)->toHaveKey('prefix');
            expect($cache['store'])->toBe('default');
            expect($cache['prefix'])->toBe('prism_transformer');
        });
    });

    describe('environment variable integration', function () {
        test('default provider can be overridden by environment', function () {
            // Test that the configuration uses env() properly
            $configPath = __DIR__.'/../../config/prism-transformer.php';
            $configContent = file_get_contents($configPath);

            expect($configContent)->toContain("env('PRISM_TRANSFORMER_DEFAULT_PROVIDER'");
        });

        test('provider models can be overridden by environment', function () {
            $configPath = __DIR__.'/../../config/prism-transformer.php';
            $configContent = file_get_contents($configPath);

            expect($configContent)->toContain("env('PRISM_TRANSFORMER_OPENAI_MODEL'");
            expect($configContent)->toContain("env('PRISM_TRANSFORMER_ANTHROPIC_MODEL'");
        });

        test('timeout settings can be overridden by environment', function () {
            $configPath = __DIR__.'/../../config/prism-transformer.php';
            $configContent = file_get_contents($configPath);

            expect($configContent)->toContain("env('PRISM_TRANSFORMER_HTTP_TIMEOUT'");
            expect($configContent)->toContain("env('PRISM_TRANSFORMER_CONNECT_TIMEOUT'");
        });
    });

    describe('configuration validation', function () {
        test('provider enum import is present', function () {
            $configPath = __DIR__.'/../../config/prism-transformer.php';
            $configContent = file_get_contents($configPath);

            expect($configContent)->toContain('use Droath\PrismTransformer\Enums\Provider');
        });

        test('configuration uses Provider enum for default', function () {
            $configPath = __DIR__.'/../../config/prism-transformer.php';
            $configContent = file_get_contents($configPath);

            expect($configContent)->toContain('Provider::OPENAI');
        });

        test('all required configuration sections exist', function () {
            $config = config('prism-transformer');

            $requiredSections = [
                'default_provider',
                'providers',
                'content_fetcher',
                'transformation',
                'cache',
            ];

            foreach ($requiredSections as $section) {
                expect($config)->toHaveKey($section);
            }
        });
    });

    describe('configuration consistency', function () {
        test('provider enum values match configuration keys', function () {
            $providers = config('prism-transformer.providers');
            $providerKeys = array_keys($providers);

            // Get all Provider enum values
            $enumValues = array_map(
                fn ($case) => $case->value,
                Provider::cases()
            );

            // Check that all enum values have corresponding config
            foreach ($enumValues as $enumValue) {
                expect($providerKeys)->toContain($enumValue);
            }
        });

        test('default provider exists in providers configuration', function () {
            $defaultProvider = config('prism-transformer.default_provider');
            $providers = config('prism-transformer.providers');

            expect($providers)->toHaveKey($defaultProvider->value);
        });
    });
});
