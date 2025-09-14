<?php

declare(strict_types=1);

use Droath\PrismTransformer\Contracts\ContentFetcherInterface;
use Droath\PrismTransformer\Exceptions\FetchException;

describe('ContentFetcherInterface Contract', function () {
    test('interface exists and has required methods', function () {
        expect(interface_exists(ContentFetcherInterface::class))->toBeTrue();

        $reflection = new ReflectionClass(ContentFetcherInterface::class);
        expect($reflection->hasMethod('fetch'))->toBeTrue();
    });

    test('fetch method has correct signature', function () {
        $reflection = new ReflectionClass(ContentFetcherInterface::class);
        $method = $reflection->getMethod('fetch');

        expect($method->getParameters())->toHaveCount(1);
        expect($method->getParameters()[0]->getName())->toBe('source');

        // Check return type is string
        $returnType = $method->getReturnType();
        expect($returnType)->not->toBeNull();
        expect($returnType->getName())->toBe('string');
    });

    test('fetch method declares FetchException in docblock', function () {
        $reflection = new ReflectionClass(ContentFetcherInterface::class);
        $method = $reflection->getMethod('fetch');
        $docComment = $method->getDocComment();

        expect($docComment)->toContain('@throws');
        expect($docComment)->toContain('FetchException');
    });
});

describe('ContentFetcherInterface Implementation Contract', function () {
    beforeEach(function () {
        $this->fetcher = new class implements ContentFetcherInterface
        {
            public function fetch(mixed $source): string
            {
                if ($source === 'invalid-url') {
                    throw new FetchException('Failed to fetch content from source');
                }

                if (is_string($source) && str_starts_with($source, 'http')) {
                    return "Content from: {$source}";
                }

                if (is_array($source)) {
                    return json_encode($source);
                }

                return (string) $source;
            }
        };
    });

    test('concrete implementation can fetch from valid URL', function () {
        $result = $this->fetcher->fetch('https://example.com');

        expect($result)->toBe('Content from: https://example.com');
    });

    test('concrete implementation throws FetchException on invalid source', function () {
        expect(fn () => $this->fetcher->fetch('invalid-url'))
            ->toThrow(FetchException::class, 'Failed to fetch content from source');
    });

    test('concrete implementation handles various source types', function () {
        $stringResult = $this->fetcher->fetch('text content');
        $arrayResult = $this->fetcher->fetch(['key' => 'value']);
        $intResult = $this->fetcher->fetch(123);

        expect($stringResult)->toBe('text content');
        expect($arrayResult)->toBe('{"key":"value"}');
        expect($intResult)->toBe('123');
    });

    test('concrete implementation always returns string', function () {
        $results = [
            $this->fetcher->fetch('string'),
            $this->fetcher->fetch(['array']),
            $this->fetcher->fetch(42),
            $this->fetcher->fetch(true),
        ];

        foreach ($results as $result) {
            expect($result)->toBeString();
        }
    });
});
