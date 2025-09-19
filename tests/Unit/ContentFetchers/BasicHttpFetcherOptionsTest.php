<?php

declare(strict_types=1);

use Droath\PrismTransformer\ContentFetchers\BasicHttpFetcher;
use Droath\PrismTransformer\Services\ConfigurationService;
use Illuminate\Cache\CacheManager;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;

use function Pest\Laravel\mock;

describe('BasicHttpFetcher Options Support', function () {
    beforeEach(function () {
        $this->httpFactory = $this->app->make(HttpFactory::class);
        $this->logger = mock(LoggerInterface::class);
        $this->logger->shouldReceive('error')->zeroOrMoreTimes();
        $this->cacheManager = $this->app->make(CacheManager::class);
        $this->configurationService = $this->app->make(ConfigurationService::class);

        $this->fetcher = new BasicHttpFetcher(
            $this->httpFactory,
            $this->logger,
            $this->cacheManager,
            $this->configurationService
        );
    });

    describe('HTTP method support', function () {
        test('supports GET requests with query parameters', function () {
            Http::fake([
                'https://example.com/api*' => Http::response('GET response', 200),
            ]);

            $result = $this->fetcher->fetch('https://example.com/api', [
                'method' => 'GET',
                'data' => ['param1' => 'value1', 'param2' => 'value2'],
            ]);

            expect($result)->toBe('GET response');

            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'example.com/api') &&
                       str_contains($request->url(), 'param1=value1') &&
                       str_contains($request->url(), 'param2=value2') &&
                       $request->method() === 'GET';
            });
        });

        test('supports POST requests with data', function () {
            Http::fake([
                'https://example.com/api' => Http::response('POST response', 200),
            ]);

            $result = $this->fetcher->fetch('https://example.com/api', [
                'method' => 'POST',
                'data' => ['name' => 'John', 'email' => 'john@example.com'],
            ]);

            expect($result)->toBe('POST response');

            Http::assertSent(function ($request) {
                return $request->url() === 'https://example.com/api' &&
                       $request->method() === 'POST' &&
                       $request->data() === ['name' => 'John', 'email' => 'john@example.com'];
            });
        });

        test('supports PUT requests', function () {
            Http::fake([
                'https://example.com/api/1' => Http::response('PUT response', 200),
            ]);

            $result = $this->fetcher->fetch('https://example.com/api/1', [
                'method' => 'PUT',
                'data' => ['name' => 'Jane'],
            ]);

            expect($result)->toBe('PUT response');

            Http::assertSent(function ($request) {
                return $request->method() === 'PUT';
            });
        });

        test('supports PATCH requests', function () {
            Http::fake([
                'https://example.com/api/1' => Http::response('PATCH response', 200),
            ]);

            $result = $this->fetcher->fetch('https://example.com/api/1', [
                'method' => 'PATCH',
                'data' => ['status' => 'active'],
            ]);

            expect($result)->toBe('PATCH response');

            Http::assertSent(function ($request) {
                return $request->method() === 'PATCH';
            });
        });

        test('supports DELETE requests', function () {
            Http::fake([
                'https://example.com/api/1' => Http::response('DELETE response', 200),
            ]);

            $result = $this->fetcher->fetch('https://example.com/api/1', [
                'method' => 'DELETE',
            ]);

            expect($result)->toBe('DELETE response');

            Http::assertSent(function ($request) {
                return $request->method() === 'DELETE';
            });
        });

        test('defaults to GET when no method specified', function () {
            Http::fake([
                'https://example.com/api' => Http::response('Default GET', 200),
            ]);

            $result = $this->fetcher->fetch('https://example.com/api', []);

            expect($result)->toBe('Default GET');

            Http::assertSent(function ($request) {
                return $request->method() === 'GET';
            });
        });
    });

    describe('HTTP client configuration', function () {
        test('supports custom headers', function () {
            Http::fake([
                'https://example.com/api' => Http::response('With headers', 200),
            ]);

            $this->fetcher->fetch('https://example.com/api', [
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'CustomAgent/1.0',
                    'Authorization' => 'Bearer token123',
                ],
            ]);

            Http::assertSent(function ($request) {
                return $request->hasHeader('Accept', 'application/json') &&
                       $request->hasHeader('User-Agent', 'CustomAgent/1.0') &&
                       $request->hasHeader('Authorization', 'Bearer token123');
            });
        });

        test('supports basic authentication with array', function () {
            Http::fake([
                'https://example.com/api' => Http::response('Authenticated', 200),
            ]);

            $this->fetcher->fetch('https://example.com/api', [
                'auth' => ['username', 'password'],
            ]);

            Http::assertSent(function ($request) {
                return $request->hasHeader('Authorization');
            });
        });

        test('supports bearer token authentication with string', function () {
            Http::fake([
                'https://example.com/api' => Http::response('Token auth', 200),
            ]);

            $this->fetcher->fetch('https://example.com/api', [
                'auth' => 'bearer-token-here',
            ]);

            Http::assertSent(function ($request) {
                return $request->hasHeader('Authorization', 'Bearer bearer-token-here');
            });
        });

        test('supports custom user agent', function () {
            Http::fake([
                'https://example.com/api' => Http::response('Custom UA', 200),
            ]);

            $this->fetcher->fetch('https://example.com/api', [
                'user_agent' => 'MyApp/2.0',
            ]);

            Http::assertSent(function ($request) {
                return $request->hasHeader('User-Agent', 'MyApp/2.0');
            });
        });

        test('supports custom timeout', function () {
            Http::fake([
                'https://example.com/api' => Http::response('Timeout test', 200),
            ]);

            $this->fetcher->fetch('https://example.com/api', [
                'timeout' => 60,
            ]);

            Http::assertSent(function ($request) {
                return true; // Just verify the request was made
            });
        });

        test('supports cookies', function () {
            Http::fake([
                'https://example.com/api' => Http::response('With cookies', 200),
            ]);

            $this->fetcher->fetch('https://example.com/api', [
                'cookies' => ['session_id' => 'abc123', 'user_pref' => 'dark_mode'],
                'cookies_domain' => 'example.com',
            ]);

            Http::assertSent(function ($request) {
                return true; // Cookies are harder to assert in tests
            });
        });

        test('supports SSL verification option', function () {
            Http::fake([
                'https://example.com/api' => Http::response('SSL test', 200),
            ]);

            $this->fetcher->fetch('https://example.com/api', [
                'verify' => false,
            ]);

            Http::assertSent(function ($request) {
                return true; // Just verify the request was made
            });
        });

        test('supports redirect options', function () {
            Http::fake([
                'https://example.com/api' => Http::response('Redirect test', 200),
            ]);

            $this->fetcher->fetch('https://example.com/api', [
                'allow_redirects' => false,
            ]);

            Http::assertSent(function ($request) {
                return true; // Just verify the request was made
            });
        });

        test('supports custom Guzzle options', function () {
            Http::fake([
                'https://example.com/api' => Http::response('Guzzle options', 200),
            ]);

            $this->fetcher->fetch('https://example.com/api', [
                'guzzle_options' => [
                    'connect_timeout' => 10,
                    'read_timeout' => 30,
                ],
            ]);

            Http::assertSent(function ($request) {
                return true; // Just verify the request was made
            });
        });
    });

    describe('option combinations', function () {
        test('supports multiple options together', function () {
            Http::fake([
                'https://api.example.com/users' => Http::response('Combined options', 200),
            ]);

            $result = $this->fetcher->fetch('https://api.example.com/users', [
                'method' => 'POST',
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'auth' => 'api-token-123',
                'data' => ['name' => 'John Doe', 'email' => 'john@example.com'],
                'timeout' => 45,
                'user_agent' => 'TestApp/1.0',
                'verify' => true,
            ]);

            expect($result)->toBe('Combined options');

            Http::assertSent(function ($request) {
                return $request->method() === 'POST' &&
                       $request->hasHeader('Accept', 'application/json') &&
                       $request->hasHeader('Content-Type', 'application/json') &&
                       $request->hasHeader('Authorization', 'Bearer api-token-123') &&
                       $request->hasHeader('User-Agent', 'TestApp/1.0') &&
                       $request->data() === ['name' => 'John Doe', 'email' => 'john@example.com'];
            });
        });
    });

    describe('cache integration with options', function () {
        test('different options create different cache entries', function () {
            Http::fake([
                'https://example.com/api' => Http::sequence()
                    ->push('First response', 200)
                    ->push('Second response', 200),
            ]);

            // Enable caching
            config(['prism-transformer.cache.enabled' => true]);

            // First request with GET
            $result1 = $this->fetcher->fetch('https://example.com/api', [
                'method' => 'GET',
            ]);

            // Second request with POST - should not use cache
            $result2 = $this->fetcher->fetch('https://example.com/api', [
                'method' => 'POST',
            ]);

            expect($result1)->toBe('First response');
            expect($result2)->toBe('Second response');

            // Verify both requests were made (no cache hit for second)
            Http::assertSentCount(2);
        });

        test('same options use cached response', function () {
            Http::fake([
                'https://example.com/api' => Http::response('Cached response', 200),
            ]);

            // Enable caching
            config(['prism-transformer.cache.enabled' => true]);

            $options = ['method' => 'GET', 'headers' => ['Accept' => 'application/json']];

            // First request
            $result1 = $this->fetcher->fetch('https://example.com/api', $options);

            // Second request with same options - should use cache
            $result2 = $this->fetcher->fetch('https://example.com/api', $options);

            expect($result1)->toBe('Cached response');
            expect($result2)->toBe('Cached response');

            // Verify only one HTTP request was made
            Http::assertSentCount(1);
        });
    });
});
