<?php

declare(strict_types=1);

use Droath\PrismTransformer\ContentFetchers\BasicHttpFetcher;
use Droath\PrismTransformer\Exceptions\FetchException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;

describe('BasicHttpFetcher URL Validation and Sanitization', function () {
    beforeEach(function () {
        $this->httpFactory = mock(HttpFactory::class);
        $this->logger = mock(LoggerInterface::class);

        $this->fetcher = new BasicHttpFetcher(
            httpFactory: $this->httpFactory,
            logger: $this->logger
        );
    });

    describe('Valid URL formats', function () {
        test('accepts standard HTTP URLs', function ($validUrl) {
            // We're just testing validation here, so we won't mock the HTTP response
            // Instead, we'll let it fail at the HTTP level to confirm URL validation passed
            $this->httpFactory->shouldReceive('timeout')->andReturn($this->httpFactory);
            $this->httpFactory->shouldReceive('get')->andThrow(new \Exception('Expected failure'));

            expect(fn () => $this->fetcher->fetch($validUrl))
                ->toThrow(\Exception::class, 'Expected failure');
        })->with([
            'simple HTTP' => ['http://example.com'],
            'simple HTTPS' => ['https://example.com'],
            'with path' => ['https://example.com/path'],
            'with query' => ['https://example.com?query=value'],
            'with fragment' => ['https://example.com#fragment'],
            'with port' => ['https://example.com:8080'],
            'with subdomain' => ['https://sub.example.com'],
            'with user info' => ['https://user:pass@example.com'],
            'complex URL' => ['https://user:pass@sub.example.com:8080/path/to/resource?query=value&other=param#section'],
        ]);

        test('accepts international domain names', function ($internationalUrl) {
            $this->httpFactory->shouldReceive('timeout')->andReturn($this->httpFactory);
            $this->httpFactory->shouldReceive('get')->andThrow(new \Exception('Expected failure'));

            expect(fn () => $this->fetcher->fetch($internationalUrl))
                ->toThrow(\Exception::class, 'Expected failure');
        })->with([
            'unicode domain' => ['https://例え.テスト'],
            'punycode domain' => ['https://xn--r8jz45g.xn--zckzah'],
        ]);

        test('accepts URLs with special characters in path and query', function ($urlWithSpecialChars) {
            $this->httpFactory->shouldReceive('timeout')->andReturn($this->httpFactory);
            $this->httpFactory->shouldReceive('get')->andThrow(new \Exception('Expected failure'));

            expect(fn () => $this->fetcher->fetch($urlWithSpecialChars))
                ->toThrow(\Exception::class, 'Expected failure');
        })->with([
            'encoded spaces' => ['https://example.com/path%20with%20spaces'],
            'encoded special chars' => ['https://example.com/path?query=%3Ctest%3E'],
            'hyphenated path' => ['https://example.com/multi-word-path'],
            'underscored query' => ['https://example.com?param_name=value'],
        ]);
    });

    describe('Invalid URL formats', function () {
        test('rejects malformed URLs', function ($malformedUrl, $expectedMessage) {
            expect(fn () => $this->fetcher->fetch($malformedUrl))
                ->toThrow(FetchException::class, $expectedMessage);
        })->with([
            'no protocol' => ['example.com', 'Invalid URL format'],
            'missing domain' => ['https://', 'Invalid URL format'],
            'empty protocol' => ['://example.com', 'Invalid URL format'],
            'invalid protocol' => ['ht@tp://example.com', 'Invalid URL format'],
            'spaces in domain' => ['https://exam ple.com', 'Invalid URL format'],
            'double slashes in path' => ['https://example.com//path', 'Invalid URL format'],
            'invalid port' => ['https://example.com:abc', 'Invalid URL format'],
        ]);

        test('rejects unsupported protocols', function ($unsupportedUrl) {
            expect(fn () => $this->fetcher->fetch($unsupportedUrl))
                ->toThrow(FetchException::class, 'Invalid URL format');
        })->with([
            'FTP' => ['ftp://example.com/file.txt'],
            'file' => ['file:///path/to/file.txt'],
            'data' => ['data:text/plain;base64,SGVsbG8='],
            'javascript' => ['javascript:alert("xss")'],
            'mailto' => ['mailto:user@example.com'],
            'tel' => ['tel:+1234567890'],
            'custom protocol' => ['myprotocol://example.com'],
        ]);

        test('rejects potentially dangerous URLs', function ($dangerousUrl) {
            expect(fn () => $this->fetcher->fetch($dangerousUrl))
                ->toThrow(FetchException::class, 'Invalid URL format');
        })->with([
            'javascript scheme' => ['javascript:alert("XSS")'],
            'data scheme with script' => ['data:text/html,<script>alert("XSS")</script>'],
            'vbscript scheme' => ['vbscript:msgbox("XSS")'],
        ]);
    });

    describe('Edge cases and security', function () {
        test('handles extremely long URLs', function () {
            // Create a very long URL (over 2048 characters)
            $longPath = str_repeat('a', 2000);
            $longUrl = "https://example.com/{$longPath}";

            $this->httpFactory->shouldReceive('timeout')->andReturn($this->httpFactory);
            $this->httpFactory->shouldReceive('get')->andThrow(new \Exception('Expected failure'));

            expect(fn () => $this->fetcher->fetch($longUrl))
                ->toThrow(\Exception::class, 'Expected failure');
        });

        test('rejects URLs with only whitespace', function ($whitespaceUrl) {
            expect(fn () => $this->fetcher->fetch($whitespaceUrl))
                ->toThrow(FetchException::class, 'Invalid URL format');
        })->with([
            'spaces' => ['   '],
            'tabs' => ["\t\t\t"],
            'newlines' => ["\n\n\n"],
            'mixed whitespace' => [" \t\n "],
        ]);

        test('handles URLs with percent-encoded characters correctly', function () {
            $encodedUrl = 'https://example.com/search?q=hello%20world&type=json';

            $this->httpFactory->shouldReceive('timeout')->andReturn($this->httpFactory);
            $this->httpFactory->shouldReceive('get')->andThrow(new \Exception('Expected failure'));

            expect(fn () => $this->fetcher->fetch($encodedUrl))
                ->toThrow(\Exception::class, 'Expected failure');
        });

        test('preserves case sensitivity in URLs', function () {
            $caseSensitiveUrl = 'https://Example.COM/Path/To/Resource';

            $this->httpFactory->shouldReceive('timeout')->andReturn($this->httpFactory);
            $this->httpFactory->shouldReceive('get')->andThrow(new \Exception('Expected failure'));

            expect(fn () => $this->fetcher->fetch($caseSensitiveUrl))
                ->toThrow(\Exception::class, 'Expected failure');
        });

        test('validates IP addresses in URLs', function ($ipUrl) {
            $this->httpFactory->shouldReceive('timeout')->andReturn($this->httpFactory);
            $this->httpFactory->shouldReceive('get')->andThrow(new \Exception('Expected failure'));

            expect(fn () => $this->fetcher->fetch($ipUrl))
                ->toThrow(\Exception::class, 'Expected failure');
        })->with([
            'IPv4' => ['https://192.168.1.1'],
            'IPv4 with port' => ['https://192.168.1.1:8080'],
            'localhost' => ['https://localhost'],
            'localhost with port' => ['https://localhost:3000'],
        ]);

        test('handles IPv6 addresses in URLs', function ($ipv6Url) {
            $this->httpFactory->shouldReceive('timeout')->andReturn($this->httpFactory);
            $this->httpFactory->shouldReceive('get')->andThrow(new \Exception('Expected failure'));

            expect(fn () => $this->fetcher->fetch($ipv6Url))
                ->toThrow(\Exception::class, 'Expected failure');
        })->with([
            'IPv6 full' => ['https://[2001:0db8:85a3:0000:0000:8a2e:0370:7334]'],
            'IPv6 compressed' => ['https://[2001:db8:85a3::8a2e:370:7334]'],
            'IPv6 with port' => ['https://[::1]:8080'],
        ]);
    });

    describe('URL normalization behavior', function () {
        test('handles various valid URL structures without normalization', function () {
            // These URLs should pass validation exactly as provided
            $urls = [
                'https://example.com/PATH',          // Uppercase path
                'https://EXAMPLE.COM/path',          // Uppercase domain
                'https://example.com:443/secure',    // Default HTTPS port
                'https://example.com/path?a=1&b=2',  // Multiple query params
                'https://example.com/path#section1', // Fragment identifier
            ];

            foreach ($urls as $url) {
                $this->httpFactory->shouldReceive('timeout')->andReturn($this->httpFactory);
                $this->httpFactory->shouldReceive('get')->andThrow(new \Exception('Expected failure'));

                expect(fn () => $this->fetcher->fetch($url))
                    ->toThrow(\Exception::class, 'Expected failure');
            }
        });
    });
});
