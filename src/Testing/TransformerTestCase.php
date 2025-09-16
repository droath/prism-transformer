<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Testing;

use Orchestra\Testbench\TestCase;
use Droath\PrismTransformer\PrismTransformerServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;

/**
 * Base test case for PrismTransformer package tests.
 *
 * This class extends Orchestra Testbench to provide a complete Laravel
 * application environment for testing. It includes the PrismTransformer
 * service provider, testing helpers, and common test setup.
 *
 * @example Basic usage:
 * ```php
 * use Droath\PrismTransformer\Testing\TransformerTestCase;
 *
 * class MyTransformerTest extends TransformerTestCase
 * {
 *     public function test_transformer_works()
 *     {
 *         $result = $this->transformText('test content')
 *             ->using($this->createMockTransformer())
 *             ->transform();
 *
 *         $this->assertTransformationSucceeded($result);
 *     }
 * }
 * ```
 *
 * @api
 */
abstract class TransformerTestCase extends TestCase
{
    use TransformerTestingHelpers;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPrismTransformerConfig();
        $this->setUpTestCache();
    }

    /**
     * Get package providers.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            PrismTransformerServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Set up test database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up cache for testing
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', [
            'driver' => 'array',
            'serialize' => false,
        ]);
    }

    /**
     * Set up PrismTransformer configuration for testing.
     */
    protected function setUpPrismTransformerConfig(): void
    {
        Config::set('prism-transformer.default_provider', 'openai');
        Config::set('prism-transformer.cache.enabled', true);
        Config::set('prism-transformer.cache.store', 'array');
        Config::set('prism-transformer.cache.prefix', 'test_prism');
        Config::set('prism-transformer.cache.ttl.content_fetch', 300);
        Config::set('prism-transformer.cache.ttl.transformer_data', 600);

        // Set up provider configurations
        Config::set('prism-transformer.providers.openai', [
            'default_model' => 'gpt-4o-mini',
            'max_tokens' => 1000,
            'temperature' => 0.7,
        ]);

        Config::set('prism-transformer.providers.anthropic', [
            'default_model' => 'claude-3-5-haiku-20241022',
            'max_tokens' => 1000,
            'temperature' => 0.7,
        ]);

        // Content fetcher settings
        Config::set('prism-transformer.content_fetcher', [
            'timeout' => 10,
            'max_redirects' => 3,
            'connect_timeout' => 5,
            'user_agent' => 'PrismTransformerTest/1.0',
        ]);
    }

    /**
     * Set up test cache configuration.
     */
    protected function setUpTestCache(): void
    {
        Cache::flush();
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    /**
     * Disable caching for the current test.
     */
    protected function disableCache(): void
    {
        Config::set('prism-transformer.cache.enabled', false);
    }

    /**
     * Enable caching for the current test (default).
     */
    protected function enableCache(): void
    {
        Config::set('prism-transformer.cache.enabled', true);
    }

    /**
     * Set the default provider for testing.
     *
     * @param string $provider Provider name (openai, anthropic, etc.)
     */
    protected function setDefaultProvider(string $provider): void
    {
        Config::set('prism-transformer.default_provider', $provider);
    }

    /**
     * Set a specific model for a provider.
     *
     * @param string $provider Provider name
     * @param string $model Model name
     */
    protected function setProviderModel(string $provider, string $model): void
    {
        Config::set("prism-transformer.providers.{$provider}.default_model", $model);
    }

    /**
     * Create test configuration for a provider.
     *
     * @param string $provider Provider name
     * @param array<string, mixed> $config Provider configuration
     */
    protected function configureProvider(string $provider, array $config): void
    {
        Config::set("prism-transformer.providers.{$provider}", array_merge([
            'default_model' => 'test-model',
            'max_tokens' => 1000,
            'temperature' => 0.7,
        ], $config));
    }

    /**
     * Assert that cache contains a specific key.
     *
     * @param string $key Cache key to check
     * @param string $message Custom assertion message
     */
    protected function assertCacheHas(string $key, string $message = ''): void
    {
        $this->assertTrue(
            Cache::has($key),
            $message ?: "Cache should contain key: {$key}"
        );
    }

    /**
     * Assert that cache does not contain a specific key.
     *
     * @param string $key Cache key to check
     * @param string $message Custom assertion message
     */
    protected function assertCacheDoesNotHave(string $key, string $message = ''): void
    {
        $this->assertFalse(
            Cache::has($key),
            $message ?: "Cache should not contain key: {$key}"
        );
    }

    /**
     * Get all cache keys (for array driver only).
     *
     * @return array<string> Array of cache keys
     */
    protected function getCacheKeys(): array
    {
        $store = Cache::getStore();

        if (method_exists($store, 'getMemory')) {
            return array_keys($store->getMemory());
        }

        return [];
    }

    /**
     * Assert that the cache is empty.
     *
     * @param string $message Custom assertion message
     */
    protected function assertCacheEmpty(string $message = 'Cache should be empty'): void
    {
        $keys = $this->getCacheKeys();
        $this->assertEmpty($keys, $message.'. Found keys: '.implode(', ', $keys));
    }

    /**
     * Create a test configuration array for use in tests.
     *
     * @param array<string, mixed> $overrides Configuration overrides
     *
     * @return array<string, mixed> Test configuration
     */
    protected function getTestConfig(array $overrides = []): array
    {
        return array_merge([
            'default_provider' => 'openai',
            'cache' => [
                'enabled' => true,
                'store' => 'array',
                'prefix' => 'test_prism',
                'ttl' => [
                    'content_fetch' => 300,
                    'transformer_data' => 600,
                ],
            ],
            'providers' => [
                'openai' => [
                    'default_model' => 'gpt-4o-mini',
                    'max_tokens' => 1000,
                    'temperature' => 0.7,
                ],
            ],
        ], $overrides);
    }

    /**
     * Assert that a configuration value is set correctly.
     *
     * @param string $key Configuration key
     * @param mixed $expected Expected value
     * @param string $message Custom assertion message
     */
    protected function assertConfigEquals(string $key, mixed $expected, string $message = ''): void
    {
        $actual = Config::get($key);
        $this->assertEquals(
            $expected,
            $actual,
            $message ?: "Configuration {$key} should equal expected value"
        );
    }

    /**
     * Simulate a slow network response for testing timeouts.
     *
     * @param string $url URL to mock
     * @param int $delayMs Delay in milliseconds
     * @param string $content Response content
     */
    protected function mockSlowHttpResponse(string $url, int $delayMs = 5000, string $content = 'Slow response'): void
    {
        // This would need to be implemented with HTTP client mocking
        // For now, we'll just mock a regular response
        $this->mockHttpResponse($url, $content, 200, [
            'X-Response-Time' => (string) $delayMs,
        ]);
    }
}
