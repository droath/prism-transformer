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
        expect($method->getParameters()[0]->getName())->toBe('url');

        // Check parameter type is string
        $paramType = $method->getParameters()[0]->getType();
        expect($paramType)->not->toBeNull();
        expect($paramType->getName())->toBe('string');

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
            public function fetch(string $url): string
            {
                if ($url === 'invalid-url') {
                    throw new FetchException('Failed to fetch content from source');
                }

                if (str_starts_with($url, 'http')) {
                    return "Content from: {$url}";
                }

                return $url;
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

    test('concrete implementation handles various URL formats', function () {
        $httpResult = $this->fetcher->fetch('https://example.com');
        $httpsResult = $this->fetcher->fetch('http://example.com');
        $pathResult = $this->fetcher->fetch('https://example.com/path');

        expect($httpResult)->toBe('Content from: https://example.com');
        expect($httpsResult)->toBe('Content from: http://example.com');
        expect($pathResult)->toBe('Content from: https://example.com/path');
    });

    test('concrete implementation always returns string', function () {
        $results = [
            $this->fetcher->fetch('https://example.com'),
            $this->fetcher->fetch('http://test.com'),
            $this->fetcher->fetch('plain-string'),
        ];

        foreach ($results as $result) {
            expect($result)->toBeString();
        }
    });
});
