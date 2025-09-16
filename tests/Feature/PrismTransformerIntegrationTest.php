<?php

declare(strict_types=1);

use Droath\PrismTransformer\PrismTransformer;
use Droath\PrismTransformer\Contracts\ContentFetcherInterface;
use Droath\PrismTransformer\ValueObjects\TransformerResult;

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
            $transformer = new PrismTransformer();
            $result = $transformer->url($url);

            expect($result)->toBe($transformer);

            // Verify content was set (current implementation returns URL)
            $reflection = new ReflectionClass($transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);
            expect($contentProperty->getValue($transformer))->toBe($url);
        });

        test('url method accepts custom ContentFetcherInterface', function () {
            $url = 'https://example.com';
            $customFetcher = mock(ContentFetcherInterface::class);

            $transformer = new PrismTransformer();
            $result = $transformer->url($url, $customFetcher);

            expect($result)->toBe($transformer);

            // Verify content was set
            $reflection = new ReflectionClass($transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);
            expect($contentProperty->getValue($transformer))->toBe($url);
        });
    });

    describe('complete transformation workflows', function () {
        test('text to transformation pipeline', function () {
            $content = 'Hello, world!';
            $transformedContent = 'HELLO, WORLD!';

            $transformer = new PrismTransformer();

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

            $closure = function ($input) use ($url, $transformedContent) {
                // Current implementation passes the URL as content
                expect($input)->toBe($url);

                return TransformerResult::successful($transformedContent);
            };

            $transformer = new PrismTransformer();
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

            $transformer = new PrismTransformer();

            $closure = function ($input) use ($content, $transformedContent) {
                expect($input)->toBe($content);

                return TransformerResult::successful($transformedContent);
            };

            $result = $transformer
                ->text($content)
                ->async()
                ->using($closure)
                ->transform();

            expect($result)->toBeInstanceOf(TransformerResult::class);
            expect($result->isSuccessful())->toBeTrue();
            expect($result->data)->toBe($transformedContent);

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
            $transformer = new PrismTransformer();

            $transformer->text($content)->using('NonExistentTransformerClass');

            expect(fn () => $transformer->transform())
                ->toThrow(\InvalidArgumentException::class, 'Invalid transformer handler provided.');
        });
    });

    describe('complex scenarios', function () {
        test('multiple transformations with same instance', function () {
            $transformer = new PrismTransformer();

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
            $transformer = new PrismTransformer();
            $url = 'https://example.com';

            $closure = fn ($content) => TransformerResult::successful('Processed: '.$content);

            // Start with text, then override with URL
            $result = $transformer
                ->text('Initial text')
                ->url($url) // This should override text content
                ->using($closure)
                ->transform();

            expect($result->data)->toBe('Processed: '.$url);
        });

        test('error handling with transformer closure exception', function () {
            $transformer = new PrismTransformer();

            $closure = function ($content) {
                throw new \Exception('Transformation error');
            };

            expect(function () use ($transformer, $closure) {
                $transformer
                    ->text('some content')
                    ->async()
                    ->using($closure)
                    ->transform();
            })->toThrow(\Exception::class, 'Transformation error');
        });

        test('null content handling in complete workflow', function () {
            $transformer = new PrismTransformer();

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

            expect($result->isSuccessful())->toBeTrue();
            expect($result->data)->toBe('Handled null content');
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

            $transformer = new PrismTransformer();

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
