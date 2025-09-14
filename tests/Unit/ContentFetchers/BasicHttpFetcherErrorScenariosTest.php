<?php

declare(strict_types=1);

use Droath\PrismTransformer\ContentFetchers\BasicHttpFetcher;
use Droath\PrismTransformer\Exceptions\FetchException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\ConnectionException;
use Psr\Log\LoggerInterface;

describe('BasicHttpFetcher Error Scenarios', function () {
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

    describe('Network and connection errors', function () {
        test('handles connection timeout gracefully', function () {
            $url = 'https://slow.example.com';

            $this->httpFactory->shouldReceive('timeout')
                ->with(30)
                ->andReturn($this->pendingRequest);

            $this->pendingRequest->shouldReceive('get')
                ->with($url)
                ->andThrow(new ConnectionException('Connection timed out'));

            $this->logger->shouldReceive('error')
                ->with('HTTP fetch failed: {message}', [
                    'message' => 'Connection timed out',
                    'url' => $url,
                ]);

            expect(fn () => $this->fetcher->fetch($url))
                ->toThrow(FetchException::class, 'Failed to fetch content from URL');
        });

        test('handles DNS resolution failure', function () {
            $url = 'https://nonexistent.domain.example';

            $this->httpFactory->shouldReceive('timeout')
                ->with(30)
                ->andReturn($this->pendingRequest);

            $this->pendingRequest->shouldReceive('get')
                ->with($url)
                ->andThrow(new ConnectionException('Could not resolve host'));

            $this->logger->shouldReceive('error')
                ->with('HTTP fetch failed: {message}', [
                    'message' => 'Could not resolve host',
                    'url' => $url,
                ]);

            expect(fn () => $this->fetcher->fetch($url))
                ->toThrow(FetchException::class, 'Failed to fetch content from URL');
        });

        test('handles SSL certificate errors', function () {
            $url = 'https://self-signed.example.com';

            $this->httpFactory->shouldReceive('timeout')
                ->with(30)
                ->andReturn($this->pendingRequest);

            $this->pendingRequest->shouldReceive('get')
                ->with($url)
                ->andThrow(new ConnectionException('SSL certificate problem'));

            $this->logger->shouldReceive('error')
                ->with('HTTP fetch failed: {message}', [
                    'message' => 'SSL certificate problem',
                    'url' => $url,
                ]);

            expect(fn () => $this->fetcher->fetch($url))
                ->toThrow(FetchException::class, 'Failed to fetch content from URL');
        });
    });

    describe('HTTP status code errors', function () {
        test('handles 404 Not Found error', function () {
            $url = 'https://example.com/not-found';

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
                ]);

            expect(fn () => $this->fetcher->fetch($url))
                ->toThrow(FetchException::class, 'HTTP request failed with status: 404');
        });

        test('handles 500 Internal Server Error', function () {
            $url = 'https://example.com/server-error';

            $this->httpFactory->shouldReceive('timeout')
                ->with(30)
                ->andReturn($this->pendingRequest);

            $this->pendingRequest->shouldReceive('get')
                ->with($url)
                ->andReturn($this->response);

            $this->response->shouldReceive('successful')
                ->andReturn(false);

            $this->response->shouldReceive('status')
                ->andReturn(500);

            $this->logger->shouldReceive('error')
                ->with('HTTP request failed with status: {status}', [
                    'status' => 500,
                    'url' => $url,
                ]);

            expect(fn () => $this->fetcher->fetch($url))
                ->toThrow(FetchException::class, 'HTTP request failed with status: 500');
        });

        test('handles 403 Forbidden error', function () {
            $url = 'https://example.com/forbidden';

            $this->httpFactory->shouldReceive('timeout')
                ->with(30)
                ->andReturn($this->pendingRequest);

            $this->pendingRequest->shouldReceive('get')
                ->with($url)
                ->andReturn($this->response);

            $this->response->shouldReceive('successful')
                ->andReturn(false);

            $this->response->shouldReceive('status')
                ->andReturn(403);

            $this->logger->shouldReceive('error')
                ->with('HTTP request failed with status: {status}', [
                    'status' => 403,
                    'url' => $url,
                ]);

            expect(fn () => $this->fetcher->fetch($url))
                ->toThrow(FetchException::class, 'HTTP request failed with status: 403');
        });

        test('handles redirect loops (too many redirects)', function () {
            $url = 'https://example.com/redirect-loop';

            $this->httpFactory->shouldReceive('timeout')
                ->with(30)
                ->andReturn($this->pendingRequest);

            $this->pendingRequest->shouldReceive('get')
                ->with($url)
                ->andThrow(new ConnectionException('Too many redirects'));

            $this->logger->shouldReceive('error')
                ->with('HTTP fetch failed: {message}', [
                    'message' => 'Too many redirects',
                    'url' => $url,
                ]);

            expect(fn () => $this->fetcher->fetch($url))
                ->toThrow(FetchException::class, 'Failed to fetch content from URL');
        });
    });

    describe('Input validation errors', function () {
        test('rejects empty string as URL', function () {
            expect(fn () => $this->fetcher->fetch(''))
                ->toThrow(FetchException::class, 'Invalid URL format');
        });

        test('rejects malformed URLs', function ($malformedUrl) {
            expect(fn () => $this->fetcher->fetch($malformedUrl))
                ->toThrow(FetchException::class, 'Invalid URL format');
        })->with([
            'no protocol' => ['example.com'],
            'wrong protocol' => ['ftp://example.com'],
            'malformed' => ['://example.com'],
            'invalid characters' => ['https://example .com'],
            'only spaces' => ['   '],
            'javascript scheme' => ['javascript:alert("xss")'],
            'data scheme' => ['data:text/plain;base64,SGVsbG8='],
        ]);

        test('interface enforces string type safety', function () {
            // With the updated interface signature (string $url),
            // PHP's type system prevents non-string inputs at compile/runtime
            // This test confirms the interface contract is properly enforced
            expect($this->fetcher)->toBeInstanceOf(\Droath\PrismTransformer\Contracts\ContentFetcherInterface::class);

            $reflection = new ReflectionClass($this->fetcher);
            $method = $reflection->getMethod('fetch');
            $param = $method->getParameters()[0];

            expect($param->getType()->getName())->toBe('string');
        });
    });

    describe('Edge case error handling', function () {
        test('handles extremely large response bodies gracefully', function () {
            $url = 'https://example.com/large-file';

            // Simulate a successful response but something goes wrong reading the body
            $this->httpFactory->shouldReceive('timeout')
                ->with(30)
                ->andReturn($this->pendingRequest);

            $this->pendingRequest->shouldReceive('get')
                ->with($url)
                ->andReturn($this->response);

            $this->response->shouldReceive('successful')
                ->andReturn(true);

            // Simulate an error when trying to get the response body
            $this->response->shouldReceive('body')
                ->andThrow(new \RuntimeException('Response body too large'));

            expect(fn () => $this->fetcher->fetch($url))
                ->toThrow(\RuntimeException::class, 'Response body too large');
        });

        test('preserves original exception chain', function () {
            $url = 'https://example.com/api';
            $originalException = new ConnectionException('Original network error');

            $this->httpFactory->shouldReceive('timeout')
                ->with(30)
                ->andReturn($this->pendingRequest);

            $this->pendingRequest->shouldReceive('get')
                ->with($url)
                ->andThrow($originalException);

            $this->logger->shouldReceive('error')
                ->with('HTTP fetch failed: {message}', [
                    'message' => 'Original network error',
                    'url' => $url,
                ]);

            try {
                $this->fetcher->fetch($url);
                expect(false)->toBeTrue('Exception should have been thrown');
            } catch (FetchException $e) {
                expect($e->getPrevious())->toBe($originalException);
                expect($e->getMessage())->toBe('Failed to fetch content from URL');
            }
        });
    });
});
