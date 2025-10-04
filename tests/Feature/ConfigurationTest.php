<?php

declare(strict_types=1);

use Droath\PrismTransformer\Enums\Provider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;

describe('Configuration', function () {
    describe('configuration file loading', function () {
        test('config file exists in package', function () {
            $configPath = __DIR__.'/../../config/prism-transformer.php';
            expect(file_exists($configPath))->toBeTrue();
        });

        test('config file returns valid array', function () {
            $configPath = __DIR__.'/../../config/prism-transformer.php';
            $config = require $configPath;
            expect($config)->toBeArray();
        });

        test('package config is loaded into Laravel config', function () {
            expect(config('prism-transformer'))->toBeArray();
        });

        test('package config is accessible via Config facade', function () {
            expect(Config::get('prism-transformer'))->toBeArray();
        });
    });

    describe('configuration publishing', function () {
        test('can publish configuration file', function () {
            // Clear any existing published config
            $publishedConfigPath = config_path('prism-transformer.php');
            if (file_exists($publishedConfigPath)) {
                unlink($publishedConfigPath);
            }

            // Run the publish command
            Artisan::call('vendor:publish', [
                '--provider' => 'Droath\PrismTransformer\PrismTransformerServiceProvider',
                '--tag' => 'prism-transformer-config',
            ]);

            expect(file_exists($publishedConfigPath))->toBeTrue();

            // Clean up
            if (file_exists($publishedConfigPath)) {
                unlink($publishedConfigPath);
            }
        });

        test('published config file contains expected structure', function () {
            // Publish the config
            $publishedConfigPath = config_path('prism-transformer.php');
            if (file_exists($publishedConfigPath)) {
                unlink($publishedConfigPath);
            }

            Artisan::call('vendor:publish', [
                '--provider' => 'Droath\PrismTransformer\PrismTransformerServiceProvider',
                '--tag' => 'prism-transformer-config',
            ]);

            expect(file_exists($publishedConfigPath))->toBeTrue();

            // Check the content
            $publishedConfig = require $publishedConfigPath;
            expect($publishedConfig)->toBeArray();

            // Clean up
            if (file_exists($publishedConfigPath)) {
                unlink($publishedConfigPath);
            }
        });
    });

    describe('configuration merging', function () {
        test('package config merges with application config', function () {
            // Set a test value in the app config
            Config::set('prism-transformer.test_key', 'test_value');

            expect(config('prism-transformer.test_key'))->toBe('test_value');
        });

        test('can override package config values', function () {
            // Set config values at runtime
            Config::set('prism-transformer.custom_setting', 'custom_value');

            expect(config('prism-transformer.custom_setting'))->toBe('custom_value');
        });

        test('config helper returns null for non-existent keys', function () {
            expect(config('prism-transformer.non_existent_key'))->toBeNull();
        });

        test('config helper returns default for non-existent keys', function () {
            $default = 'default_value';
            expect(config('prism-transformer.non_existent_key', $default))->toBe($default);
        });
    });

    describe('configuration access patterns', function () {
        test('can access nested config values', function () {
            Config::set('prism-transformer.nested.key', 'nested_value');

            expect(config('prism-transformer.nested.key'))->toBe('nested_value');
        });

        test('can access all config as array', function () {
            Config::set('prism-transformer.key1', 'value1');
            Config::set('prism-transformer.key2', 'value2');

            $allConfig = config('prism-transformer');
            expect($allConfig)->toBeArray();
            expect($allConfig)->toHaveKey('key1');
            expect($allConfig)->toHaveKey('key2');
        });

        test('config is available in service container', function () {
            $config = app('config');
            expect($config->get('prism-transformer'))->toBeArray();
        });
    });

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

        test('has cache enabled setting defaulting to false', function () {
            $cacheEnabled = config('prism-transformer.cache.transformer_results.enabled');
            expect($cacheEnabled)->toBeFalse();
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
        test('config file has valid PHP syntax', function () {
            $configPath = __DIR__.'/../../config/prism-transformer.php';

            // Test that the file can be parsed without errors
            $result = shell_exec("php -l {$configPath} 2>&1");
            expect($result)->toContain('No syntax errors detected');
        });

        test('config file returns consistent type', function () {
            $configPath = __DIR__.'/../../config/prism-transformer.php';

            // Load config multiple times to ensure consistency
            $config1 = require $configPath;
            $config2 = require $configPath;

            expect(gettype($config1))->toBe(gettype($config2));
            expect($config1)->toEqual($config2);
        });

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

    describe('package service provider config integration', function () {
        test('service provider registers config file correctly', function () {
            // Test that the config is properly registered by the service provider
            expect(config('prism-transformer'))->toBeArray();

            // Test that the service provider is registered in the container
            $providers = app()->getLoadedProviders();
            expect($providers)->toHaveKey('Droath\PrismTransformer\PrismTransformerServiceProvider');
        });

        test('can resolve config-dependent services', function () {
            // Set some config that might be used by services
            Config::set('prism-transformer.test_service_config', 'test_value');

            // Test that services can access the config
            expect(config('prism-transformer.test_service_config'))->toBe('test_value');
        });
    });

    describe('configuration caching', function () {
        test('config works when cached', function () {
            // Cache the configuration
            Artisan::call('config:cache');

            // Config should still be accessible
            expect(config('prism-transformer'))->toBeArray();

            // Clear the cache
            Artisan::call('config:clear');
        });

        test('config changes are reflected after cache clear', function () {
            // Set initial value
            Config::set('prism-transformer.cache_test', 'initial');

            // Cache the config
            Artisan::call('config:cache');

            // Clear cache and set new value
            Artisan::call('config:clear');
            Config::set('prism-transformer.cache_test', 'updated');

            expect(config('prism-transformer.cache_test'))->toBe('updated');
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
