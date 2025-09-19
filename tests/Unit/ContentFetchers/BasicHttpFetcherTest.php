<?php

declare(strict_types=1);

use Droath\PrismTransformer\ContentFetchers\BasicHttpFetcher;
use Droath\PrismTransformer\Exceptions\FetchException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Psr\Log\LoggerInterface;

describe('BasicHttpFetcher', function () {
    beforeEach(function () {
        $this->httpFactory = mock(HttpFactory::class);
        $this->pendingRequest = mock(PendingRequest::class);
        $this->response = mock(Response::class);
        $this->logger = mock(LoggerInterface::class);

        $this->fetcher = new BasicHttpFetcher(
            httpFactory: $this->httpFactory,
            logger: $this->logger
        );
    });

    test('can be instantiated with required dependencies', function () {
        expect($this->fetcher)->toBeInstanceOf(BasicHttpFetcher::class);
    });

    test('implements ContentFetcherInterface', function () {
        expect($this->fetcher)->toBeInstanceOf(\Droath\PrismTransformer\Contracts\ContentFetcherInterface::class);
    });
});

describe('BasicHttpFetcher fetch method', function () {
    beforeEach(function () {
        $this->httpFactory = mock(HttpFactory::class);
        $this->pendingRequest = mock(PendingRequest::class);
        $this->response = mock(Response::class);
        $this->logger = mock(LoggerInterface::class);

        $this->fetcher = new BasicHttpFetcher(
            httpFactory: $this->httpFactory,
            logger: $this->logger
        );
    });

    test('successfully fetches content from valid URL', function () {
        $url = 'https://example.com/api/data';
        $expectedContent = '{"message": "Hello, World!"}';

        $this->httpFactory->shouldReceive('timeout')
            ->with(30)
            ->andReturn($this->pendingRequest);

        $this->pendingRequest->shouldReceive('get')
            ->with($url)
            ->andReturn($this->response);

        $this->response->shouldReceive('successful')
            ->andReturn(true);

        $this->response->shouldReceive('body')
            ->andReturn($expectedContent);

        $result = $this->fetcher->fetch($url);

        expect($result)->toBe($expectedContent);
    });

    test('throws FetchException for invalid URL', function () {
        $invalidUrl = 'not-a-valid-url';

        expect(fn () => $this->fetcher->fetch($invalidUrl))
            ->toThrow(FetchException::class, 'Invalid URL format');
    });

    test('throws FetchException when HTTP client throws ConnectionException', function () {
        $url = 'https://example.com/api/data';

        $this->httpFactory->shouldReceive('timeout')
            ->with(30)
            ->andReturn($this->pendingRequest);

        $this->pendingRequest->shouldReceive('get')
            ->with($url)
            ->andThrow(new ConnectionException('Connection failed'));

        $this->logger->shouldReceive('error')
            ->with('HTTP fetch failed: {message}', [
                'message' => 'Connection failed',
                'url' => $url,
            ]);

        expect(fn () => $this->fetcher->fetch($url))
            ->toThrow(FetchException::class, 'Failed to fetch content from URL');
    });

    test('throws FetchException for unsuccessful HTTP response', function () {
        $url = 'https://example.com/api/data';

        $this->httpFactory->shouldReceive('timeout')
            ->with(30)
            ->andReturn($this->pendingRequest);

        $this->pendingRequest->shouldReceive('get')
            ->with($url)
            ->andReturn($this->response);

        $this->response->shouldReceive('successful')
            ->andReturn(false);

        $this->response->shouldReceive('status')
            ->andReturn(404);

        $this->logger->shouldReceive('error')
            ->with('HTTP request failed with status: {status}', [
                'status' => 404,
                'url' => $url,
                'method' => 'get',
            ]);

        expect(fn () => $this->fetcher->fetch($url))
            ->toThrow(FetchException::class, 'HTTP request failed with status: 404');
    });

    test('enforces string type through interface', function () {
        // This test verifies the interface contract is enforced at the type level
        // PHP 8+ type hints will prevent non-string values from being passed
        expect($this->fetcher)->toBeInstanceOf(\Droath\PrismTransformer\Contracts\ContentFetcherInterface::class);

        // Test with a valid string URL to confirm the interface works
        $this->httpFactory->shouldReceive('timeout')->andReturn($this->pendingRequest);
        $this->pendingRequest->shouldReceive('get')->andThrow(new \Exception('Expected'));

        expect(fn () => $this->fetcher->fetch('https://example.com'))
            ->toThrow(\Exception::class, 'Expected');
    });
});

describe('BasicHttpFetcher with custom configuration', function () {
    test('can be instantiated with custom timeout', function () {
        $httpFactory = mock(HttpFactory::class);
        $logger = mock(LoggerInterface::class);
        $customTimeout = 60;

        $fetcher = new BasicHttpFetcher(
            httpFactory: $httpFactory,
            logger: $logger,
            timeout: $customTimeout
        );

        expect($fetcher)->toBeInstanceOf(BasicHttpFetcher::class);
    });

    test('validates timeout parameter', function () {
        $httpFactory = mock(HttpFactory::class);
        $logger = mock(LoggerInterface::class);

        expect(fn () => new BasicHttpFetcher(
            httpFactory: $httpFactory,
            logger: $logger,
            timeout: 0
        ))->toThrow(InvalidArgumentException::class, 'Timeout must be positive');

        expect(fn () => new BasicHttpFetcher(
            httpFactory: $httpFactory,
            logger: $logger,
            timeout: -10
        ))->toThrow(InvalidArgumentException::class, 'Timeout must be positive');
    });

    test('uses custom timeout for HTTP requests', function () {
        $httpFactory = mock(HttpFactory::class);
        $pendingRequest = mock(PendingRequest::class);
        $response = mock(Response::class);
        $logger = mock(LoggerInterface::class);
        $customTimeout = 45;

        $fetcher = new BasicHttpFetcher(
            httpFactory: $httpFactory,
            logger: $logger,
            timeout: $customTimeout
        );

        $url = 'https://example.com';

        $httpFactory->shouldReceive('timeout')
            ->with($customTimeout)
            ->andReturn($pendingRequest);

        $pendingRequest->shouldReceive('get')
            ->with($url)
            ->andReturn($response);

        $response->shouldReceive('successful')
            ->andReturn(true);

        $response->shouldReceive('body')
            ->andReturn('success');

        $result = $fetcher->fetch($url);

        expect($result)->toBe('success');
    });

    test('uses configured timeout when no explicit timeout provided', function () {
        Config::set('prism-transformer.content_fetcher.timeout', 60);

        $httpFactory = mock(HttpFactory::class);
        $pendingRequest = mock(PendingRequest::class);
        $response = mock(Response::class);
        $logger = mock(LoggerInterface::class);
        $configuration = new \Droath\PrismTransformer\Services\ConfigurationService();

        $fetcher = new BasicHttpFetcher(
            httpFactory: $httpFactory,
            logger: $logger,
            configuration: $configuration
        );

        $url = 'https://example.com';

        $httpFactory->shouldReceive('timeout')
            ->with(60) // Should use configured timeout
            ->andReturn($pendingRequest);

        $pendingRequest->shouldReceive('get')
            ->with($url)
            ->andReturn($response);

        $response->shouldReceive('successful')
            ->andReturn(true);

        $response->shouldReceive('body')
            ->andReturn('configured timeout success');

        $result = $fetcher->fetch($url);

        expect($result)->toBe('configured timeout success');
    });
});
