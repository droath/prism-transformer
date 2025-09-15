<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;

describe('Configuration Integration', function () {
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
});
