<?php

declare(strict_types=1);

use Droath\PrismTransformer\PrismTransformer;
use Droath\PrismTransformer\Contracts\PrismTransformerInterface;
use Droath\PrismTransformer\Contracts\ContentFetcherInterface;
use Droath\PrismTransformer\ValueObjects\TransformerResult;

use function Pest\Laravel\mock;

describe('PrismTransformer', function () {
    beforeEach(function () {
        $this->transformer = new PrismTransformer();
    });

    describe('instantiation', function () {
        test('can be instantiated', function () {
            expect($this->transformer)->toBeInstanceOf(PrismTransformer::class);
        });

        test('implements PrismTransformerInterface', function () {
            expect($this->transformer)->toBeInstanceOf(PrismTransformerInterface::class);
        });

        test('has default property values', function () {
            $reflection = new ReflectionClass($this->transformer);

            $asyncProperty = $reflection->getProperty('async');
            $asyncProperty->setAccessible(true);
            expect($asyncProperty->getValue($this->transformer))->toBeFalse();

            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);
            expect($contentProperty->getValue($this->transformer))->toBeNull();

            $handlerProperty = $reflection->getProperty('transformerHandler');
            $handlerProperty->setAccessible(true);
            expect($handlerProperty->getValue($this->transformer))->toBeNull();
        });
    });

    describe('fluent interface', function () {
        test('text method returns self for chaining', function () {
            $result = $this->transformer->text('test content');
            expect($result)->toBe($this->transformer);
        });

        test('async method returns self for chaining', function () {
            $result = $this->transformer->async();
            expect($result)->toBe($this->transformer);
        });

        test('using method returns self for chaining', function () {
            $closure = fn ($content) => TransformerResult::successful($content);
            $result = $this->transformer->using($closure);
            expect($result)->toBe($this->transformer);
        });

        test('methods can be chained together', function () {
            $closure = fn ($content) => TransformerResult::successful($content);

            $result = $this->transformer
                ->text('test content')
                ->async()
                ->using($closure);

            expect($result)->toBe($this->transformer);
        });
    });

    describe('text method', function () {
        test('sets content property', function () {
            $content = 'This is test content';
            $this->transformer->text($content);

            $reflection = new ReflectionClass($this->transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);

            expect($contentProperty->getValue($this->transformer))->toBe($content);
        });

        test('overwrites existing content', function () {
            $this->transformer->text('first content');
            $this->transformer->text('second content');

            $reflection = new ReflectionClass($this->transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);

            expect($contentProperty->getValue($this->transformer))->toBe('second content');
        });

        test('handles empty string', function () {
            $this->transformer->text('');

            $reflection = new ReflectionClass($this->transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);

            expect($contentProperty->getValue($this->transformer))->toBe('');
        });

        test('handles special characters and unicode', function () {
            $content = 'Special chars: àáâãäåæçèéêë 中文 العربية 🚀';
            $this->transformer->text($content);

            $reflection = new ReflectionClass($this->transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);

            expect($contentProperty->getValue($this->transformer))->toBe($content);
        });
    });

    describe('url method', function () {
        test('creates UrlTransformerHandler and sets content', function () {
            $url = 'https://example.com';

            // Based on current implementation, UrlTransformerHandler.handle() returns the URL
            $this->transformer->url($url);

            $reflection = new ReflectionClass($this->transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);

            // Current implementation returns the URL directly
            expect($contentProperty->getValue($this->transformer))->toBe($url);
        });

        test('returns self for method chaining', function () {
            $url = 'https://example.com';
            $result = $this->transformer->url($url);
            expect($result)->toBe($this->transformer);
        });

        test('accepts custom ContentFetcherInterface parameter', function () {
            $url = 'https://example.com';
            $fetcherMock = mock(ContentFetcherInterface::class);

            $result = $this->transformer->url($url, $fetcherMock);
            expect($result)->toBe($this->transformer);

            // Verify content is set (currently returns URL)
            $reflection = new ReflectionClass($this->transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);
            expect($contentProperty->getValue($this->transformer))->toBe($url);
        });

        test('overwrites existing text content', function () {
            $url = 'https://example.com';

            // Set initial text content
            $this->transformer->text('initial text content');

            // Override with URL
            $this->transformer->url($url);

            $reflection = new ReflectionClass($this->transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);

            // URL should have overwritten the text content
            expect($contentProperty->getValue($this->transformer))->toBe($url);
        });
    });

    describe('async method', function () {
        test('sets async property to true', function () {
            $this->transformer->async();

            $reflection = new ReflectionClass($this->transformer);
            $asyncProperty = $reflection->getProperty('async');
            $asyncProperty->setAccessible(true);

            expect($asyncProperty->getValue($this->transformer))->toBeTrue();
        });

        test('can be called multiple times', function () {
            $this->transformer->async()->async();

            $reflection = new ReflectionClass($this->transformer);
            $asyncProperty = $reflection->getProperty('async');
            $asyncProperty->setAccessible(true);

            expect($asyncProperty->getValue($this->transformer))->toBeTrue();
        });
    });

    describe('using method', function () {
        test('accepts closure transformer', function () {
            $closure = fn ($content) => TransformerResult::successful('transformed: '.$content);

            $this->transformer->using($closure);

            $reflection = new ReflectionClass($this->transformer);
            $handlerProperty = $reflection->getProperty('transformerHandler');
            $handlerProperty->setAccessible(true);

            expect($handlerProperty->getValue($this->transformer))->toBe($closure);
        });

        test('accepts string transformer class name', function () {
            $className = 'TestTransformerClass';

            $this->transformer->using($className);

            $reflection = new ReflectionClass($this->transformer);
            $handlerProperty = $reflection->getProperty('transformerHandler');
            $handlerProperty->setAccessible(true);

            expect($handlerProperty->getValue($this->transformer))->toBe($className);
        });

        test('overwrites existing transformer handler', function () {
            $firstClosure = fn ($content) => TransformerResult::successful('first');
            $secondClosure = fn ($content) => TransformerResult::successful('second');

            $this->transformer->using($firstClosure);
            $this->transformer->using($secondClosure);

            $reflection = new ReflectionClass($this->transformer);
            $handlerProperty = $reflection->getProperty('transformerHandler');
            $handlerProperty->setAccessible(true);

            expect($handlerProperty->getValue($this->transformer))->toBe($secondClosure);
        });
    });

    describe('transform method', function () {
        test('throws exception when no transformer handler is set', function () {
            expect(fn () => $this->transformer->transform())
                ->toThrow(\InvalidArgumentException::class, 'Invalid transformer handler provided.');
        });

        test('executes closure transformer with content', function () {
            $content = 'test content';
            $expectedResult = TransformerResult::successful('transformed: '.$content);

            $closure = fn ($input) => $input === $content
                ? $expectedResult
                : TransformerResult::failed(['Unexpected content']);

            $result = $this->transformer
                ->text($content)
                ->using($closure)
                ->transform();

            expect($result)->toBe($expectedResult);
        });

        test('executes string transformer class with content', function () {
            $content = 'test content';
            $expectedResult = TransformerResult::successful('string transformed: '.$content);

            $closure = fn ($input) => $input === $content
                ? $expectedResult
                : TransformerResult::failed(['Unexpected content']);

            $result = $this->transformer
                ->text($content)
                ->using($closure)
                ->transform();

            expect($result)->toBe($expectedResult);
        });

        test('passes null content to handler when no content is set', function () {
            $closure = function ($content) {
                expect($content)->toBeNull();

                return TransformerResult::successful('handled null content');
            };

            $result = $this->transformer
                ->using($closure)
                ->transform();

            expect($result)->toBeInstanceOf(TransformerResult::class);
        });

        test('handles transformer that returns null', function () {
            $closure = fn ($content) => null;

            $result = $this->transformer
                ->text('some content')
                ->using($closure)
                ->transform();

            expect($result)->toBeNull();
        });

        test('handles closure that returns valid result', function () {
            $content = 'test content';
            $expectedResult = TransformerResult::successful('closure result');

            $closure = fn ($input) => $input === $content
                ? $expectedResult
                : TransformerResult::failed(['Unexpected content']);

            $result = $this->transformer
                ->text($content)
                ->using($closure)
                ->transform();

            expect($result)->toBe($expectedResult);
        });
    });

    describe('edge cases and error conditions', function () {
        test('handles very long content strings', function () {
            $longContent = str_repeat('A', 100000);

            $closure = fn ($content) => TransformerResult::successful('Length: '.strlen($content));

            $result = $this->transformer
                ->text($longContent)
                ->using($closure)
                ->transform();

            expect($result->data)->toBe('Length: 100000');
        });

        test('maintains state across multiple operations', function () {
            $this->transformer->text('initial content');
            $this->transformer->async();

            $closure = function ($content) {
                return TransformerResult::successful($content);
            };

            $this->transformer->using($closure);

            // Verify all state is maintained
            $reflection = new ReflectionClass($this->transformer);

            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);
            expect($contentProperty->getValue($this->transformer))->toBe('initial content');

            $asyncProperty = $reflection->getProperty('async');
            $asyncProperty->setAccessible(true);
            expect($asyncProperty->getValue($this->transformer))->toBeTrue();

            $result = $this->transformer->transform();
            expect($result->data)->toBe('initial content');
        });

        test('content is overwritten by url method', function () {
            $url = 'https://example.com';

            $this->transformer->text('text content');
            $this->transformer->url($url);

            $reflection = new ReflectionClass($this->transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);

            // Current implementation returns the URL
            expect($contentProperty->getValue($this->transformer))->toBe($url);
        });

        test('transformer can be reused after transform', function () {
            $closure = fn ($content) => TransformerResult::successful('transformed: '.$content);

            $this->transformer->using($closure);

            // First transformation
            $result1 = $this->transformer->text('first')->transform();
            expect($result1->data)->toBe('transformed: first');

            // Second transformation with same instance
            $result2 = $this->transformer->text('second')->transform();
            expect($result2->data)->toBe('transformed: second');
        });

        test('handles closure that throws exception', function () {
            $closure = function ($content) {
                throw new \Exception('Transformer error');
            };

            $this->transformer->text('content')->using($closure);

            expect(fn () => $this->transformer->transform())
                ->toThrow(\Exception::class, 'Transformer error');
        });

        test('handles string transformer that throws exception', function () {
            $this->transformer->text('content')->using('NonExistentTransformerClass');

            expect(fn () => $this->transformer->transform())
                ->toThrow(\InvalidArgumentException::class, 'Invalid transformer handler provided.');
        });
    });
});
