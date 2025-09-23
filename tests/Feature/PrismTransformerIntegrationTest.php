<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Droath\PrismTransformer\PrismTransformer;
use Droath\PrismTransformer\Contracts\ContentFetcherInterface;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Illuminate\Foundation\Bus\PendingDispatch;

use function Pest\Laravel\mock;

describe('PrismTransformer Integration', function () {
    describe('Laravel service container integration', function () {
        test('can be instantiated from service container', function () {
            $transformer = app(PrismTransformer::class);
            expect($transformer)->toBeInstanceOf(PrismTransformer::class);
        });

        test('creates new instance each time from container', function () {
            $transformer1 = app(PrismTransformer::class);
            $transformer2 = app(PrismTransformer::class);

            expect($transformer1)->not->toBe($transformer2);
            expect($transformer1)->toBeInstanceOf(PrismTransformer::class);
            expect($transformer2)->toBeInstanceOf(PrismTransformer::class);
        });
    });

    describe('UrlTransformerHandler integration', function () {
        test('url method creates UrlTransformerHandler with default BasicHttpFetcher', function () {
            $url = 'https://example.com';
            $expectedContent = 'Mocked fetched content from example.com';

            // Mock the BasicHttpFetcher to avoid real HTTP requests
            $mockFetcher = mock(\Droath\PrismTransformer\ContentFetchers\BasicHttpFetcher::class);
            $mockFetcher->expects('fetch')->with($url)->andReturn($expectedContent);

            // Bind the mock to the container
            $this->app->instance(\Droath\PrismTransformer\ContentFetchers\BasicHttpFetcher::class, $mockFetcher);

            $transformer = app(PrismTransformer::class);
            $result = $transformer->url($url);

            expect($result)->toBe($transformer);

            // Verify content was set with the fetched content
            $reflection = new ReflectionClass($transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);
            expect($contentProperty->getValue($transformer))->toBe($expectedContent);
        });

        test('url method accepts custom ContentFetcherInterface', function () {
            $url = 'https://example.com';
            $expectedContent = 'Content from custom fetcher';
            $customFetcher = mock(ContentFetcherInterface::class);
            $customFetcher->expects('fetch')->with($url)->andReturn($expectedContent);

            $transformer = app(PrismTransformer::class);
            $result = $transformer->url($url, $customFetcher);

            expect($result)->toBe($transformer);

            // Verify content was set with fetched content
            $reflection = new ReflectionClass($transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);
            expect($contentProperty->getValue($transformer))->toBe($expectedContent);
        });
    });

    describe('complete transformation workflows', function () {
        test('text to transformation pipeline', function () {
            $content = 'Hello, world!';
            $transformedContent = 'HELLO, WORLD!';

            $transformer = app(PrismTransformer::class);

            $closure = function ($input) use ($content, $transformedContent) {
                expect($input)->toBe($content);

                return TransformerResult::successful($transformedContent);
            };

            $result = $transformer
                ->text($content)
                ->using($closure)
                ->transform();

            expect($result)->toBeInstanceOf(TransformerResult::class);
            expect($result->isSuccessful())->toBeTrue();
            expect($result->data)->toBe($transformedContent);
        });

        test('url to transformation pipeline', function () {
            $url = 'https://api.example.com/data';
            $transformedContent = 'Processed API data';
            $fetchedContent = 'Fetched API data content';

            // Mock the BasicHttpFetcher to avoid real HTTP requests
            $mockFetcher = mock(\Droath\PrismTransformer\ContentFetchers\BasicHttpFetcher::class);
            $mockFetcher->expects('fetch')->with($url)->andReturn($fetchedContent);

            // Bind the mock to the container
            $this->app->instance(\Droath\PrismTransformer\ContentFetchers\BasicHttpFetcher::class, $mockFetcher);

            $closure = function ($input) use ($fetchedContent, $transformedContent) {
                // Current implementation passes the fetched content
                expect($input)->toBe($fetchedContent);

                return TransformerResult::successful($transformedContent);
            };

            $transformer = app(PrismTransformer::class);
            $result = $transformer
                ->url($url)
                ->using($closure)
                ->transform();

            expect($result)->toBeInstanceOf(TransformerResult::class);
            expect($result->isSuccessful())->toBeTrue();
            expect($result->data)->toBe($transformedContent);
        });

        test('async transformation workflow', function () {
            $content = 'Async content';
            $transformedContent = 'Async transformed content';

            $transformer = app(PrismTransformer::class);

            $closure = function ($input) use ($content, $transformedContent) {
                expect($input)->toBe($content);

                return TransformerResult::successful($transformedContent);
            };

            $result = $transformer
                ->text($content)
                ->async()
                ->using($closure)
                ->transform();

            expect($result)->toBeInstanceOf(PendingDispatch::class);

            // Verify async flag was set
            $reflection = new ReflectionClass($transformer);
            $asyncProperty = $reflection->getProperty('async');
            $asyncProperty->setAccessible(true);
            expect($asyncProperty->getValue($transformer))->toBeTrue();
        });
    });

    describe('String transformer integration', function () {
        test('handles invalid string transformer class', function () {
            $content = 'Test content for string transformer';
            $transformer = app(PrismTransformer::class);

            $transformer->text($content)->using('NonExistentTransformerClass');

            expect(fn () => $transformer->transform())
                ->toThrow(\InvalidArgumentException::class, 'Invalid transformer handler provided.');
        });
    });

    describe('complex scenarios', function () {
        test('multiple transformations with same instance', function () {
            $transformer = app(PrismTransformer::class);

            $uppercaseClosure = fn ($content) => TransformerResult::successful(strtoupper($content));

            // First transformation
            $result1 = $transformer
                ->text('first text')
                ->using($uppercaseClosure)
                ->transform();

            expect($result1->data)->toBe('FIRST TEXT');

            // Second transformation with different content
            $result2 = $transformer
                ->text('second text')
                ->transform(); // Reuse same transformer

            expect($result2->data)->toBe('SECOND TEXT');

            // Third transformation with different handler
            $lowercaseClosure = fn ($content) => TransformerResult::successful(strtolower($content));

            $result3 = $transformer
                ->text('THIRD TEXT')
                ->using($lowercaseClosure)
                ->transform();

            expect($result3->data)->toBe('third text');
        });

        test('url and text content mixing', function () {
            $transformer = app(PrismTransformer::class);
            $url = 'https://example.com';
            $expectedFetchedContent = 'Fetched content from example.com';

            // Mock the BasicHttpFetcher to avoid real HTTP requests
            $mockFetcher = mock(\Droath\PrismTransformer\ContentFetchers\BasicHttpFetcher::class);
            $mockFetcher->expects('fetch')->with($url)->andReturn($expectedFetchedContent);

            // Bind the mock to the container
            $this->app->instance(\Droath\PrismTransformer\ContentFetchers\BasicHttpFetcher::class, $mockFetcher);

            $closure = fn ($content) => TransformerResult::successful('Processed: '.$content);

            // Start with text, then override with URL
            $result = $transformer
                ->text('Initial text')
                ->url($url) // This should override text content
                ->using($closure)
                ->transform();

            expect($result->data)->toBe('Processed: '.$expectedFetchedContent);
        });

        test('error handling with transformer closure exception', function () {
            Queue::fake();
            $transformer = app(PrismTransformer::class);

            $closure = function ($content) {
                throw new \Exception('Transformation error');
            };

            $result = $transformer
                ->text('some content')
                ->async()
                ->using($closure)
                ->transform();

            // Async transformations return PendingDispatch, not the actual result
            expect($result)->toBeInstanceOf(PendingDispatch::class);
        });

        test('null content handling in complete workflow', function () {
            $transformer = app(PrismTransformer::class);

            $closure = function ($content) {
                if ($content === null) {
                    return TransformerResult::successful('Handled null content');
                }

                return TransformerResult::failed(['Unexpected non-null content']);
            };

            // Don't set any content - should pass null to transformer
            $result = $transformer
                ->async()
                ->using($closure)
                ->transform();

            expect($result)->toBeInstanceOf(PendingDispatch::class);
        });
    });

    describe('Laravel package integration patterns', function () {
        test('can be used as dependency in other services', function () {
            // Simulate a service that depends on PrismTransformer
            $this->app->bind('test.service', function ($app) {
                return new class($app->make(PrismTransformer::class))
                {
                    public function __construct(private PrismTransformer $transformer) {}

                    public function processText(string $text): ?TransformerResult
                    {
                        $closure = fn ($content) => TransformerResult::successful('Service processed: '.$content);

                        return $this->transformer
                            ->text($text)
                            ->using($closure)
                            ->transform();
                    }
                };
            });

            $service = app('test.service');
            $result = $service->processText('test input');

            expect($result)->toBeInstanceOf(TransformerResult::class);
            expect($result->data)->toBe('Service processed: test input');
        });

        test('handles configuration-driven transformations', function () {
            // Simulate configuration-based transformer selection
            config(['prism-transformer.default_mode' => 'uppercase']);

            $transformer = app(PrismTransformer::class);

            $configClosure = function ($content) {
                $mode = config('prism-transformer.default_mode');

                return match ($mode) {
                    'uppercase' => TransformerResult::successful(strtoupper($content)),
                    'lowercase' => TransformerResult::successful(strtolower($content)),
                    default => TransformerResult::successful($content),
                };
            };

            $result = $transformer
                ->text('Mixed Case Text')
                ->using($configClosure)
                ->transform();

            expect($result->data)->toBe('MIXED CASE TEXT');
        });
    });
});
