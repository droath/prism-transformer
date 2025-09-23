<?php

declare(strict_types=1);

use Droath\PrismTransformer\Contracts\PrismTransformerInterface;
use Droath\PrismTransformer\PrismTransformer;

describe('PrismTransformerInterface Contract', function () {
    test('PrismTransformer implements PrismTransformerInterface', function () {
        $transformer = app(PrismTransformer::class);

        expect($transformer)->toBeInstanceOf(PrismTransformerInterface::class);
    });

    describe('method signature compliance', function () {
        test('text method signature matches interface', function () {
            $transformer = app(PrismTransformer::class);

            expect($transformer->text('test content'))->toBe($transformer);
        });

        test('url method signature matches interface', function () {
            $transformer = app(PrismTransformer::class);
            $mockFetcher = mock(\Droath\PrismTransformer\Contracts\ContentFetcherInterface::class);
            $mockFetcher->allows('fetch')->andReturn('test content');

            expect($transformer->url('https://example.com', $mockFetcher))->toBe($transformer);
        });

        test('image method signature matches interface requirement', function () {
            $transformer = app(PrismTransformer::class);

            // Test method exists and returns static
            expect(method_exists($transformer, 'image'))->toBeTrue();

            // Test method signature parameters
            $reflection = new ReflectionMethod($transformer, 'image');
            $parameters = $reflection->getParameters();

            expect($parameters)->toHaveCount(2);
            expect($parameters[0]->getName())->toBe('path');
            expect($parameters[0]->getType()->getName())->toBe('string');
            expect($parameters[1]->getName())->toBe('options');
            expect($parameters[1]->getType()->getName())->toBe('array');
            expect($parameters[1]->isDefaultValueAvailable())->toBeTrue();
            expect($parameters[1]->getDefaultValue())->toBe([]);

            // Test return type
            expect($reflection->getReturnType()->getName())->toBe('static');
        });

        test('document method signature matches interface requirement', function () {
            $transformer = app(PrismTransformer::class);

            // Test method exists and returns static
            expect(method_exists($transformer, 'document'))->toBeTrue();

            // Test method signature parameters
            $reflection = new ReflectionMethod($transformer, 'document');
            $parameters = $reflection->getParameters();

            expect($parameters)->toHaveCount(2);
            expect($parameters[0]->getName())->toBe('path');
            expect($parameters[0]->getType()->getName())->toBe('string');
            expect($parameters[1]->getName())->toBe('options');
            expect($parameters[1]->getType()->getName())->toBe('array');
            expect($parameters[1]->isDefaultValueAvailable())->toBeTrue();
            expect($parameters[1]->getDefaultValue())->toBe([]);

            // Test return type
            expect($reflection->getReturnType()->getName())->toBe('static');
        });

        test('async method signature matches interface', function () {
            $transformer = app(PrismTransformer::class);

            expect($transformer->async())->toBe($transformer);
        });

        test('using method signature matches interface', function () {
            $transformer = app(PrismTransformer::class);
            $closure = fn ($content) => \Droath\PrismTransformer\ValueObjects\TransformerResult::successful($content);

            expect($transformer->using($closure))->toBe($transformer);
        });

        test('setContext method signature matches interface', function () {
            $transformer = app(PrismTransformer::class);
            $context = ['user_id' => 123];

            expect($transformer->setContext($context))->toBe($transformer);
        });

        test('transform method signature matches interface', function () {
            $transformer = app(PrismTransformer::class);
            $transformer->text('test content');

            // Transform method should exist and be callable
            expect(method_exists($transformer, 'transform'))->toBeTrue();

            $reflection = new ReflectionMethod($transformer, 'transform');
            expect($reflection->getReturnType())->not->toBeNull();
        });
    });

    describe('interface method coverage', function () {
        test('PrismTransformer implements all required interface methods', function () {
            $interfaceReflection = new ReflectionClass(PrismTransformerInterface::class);
            $implementationReflection = new ReflectionClass(PrismTransformer::class);

            $interfaceMethods = $interfaceReflection->getMethods();

            foreach ($interfaceMethods as $method) {
                expect($implementationReflection->hasMethod($method->getName()))
                    ->toBeTrue("Method {$method->getName()} should be implemented");

                $implementationMethod = $implementationReflection->getMethod($method->getName());

                // Check if method is public
                expect($implementationMethod->isPublic())
                    ->toBeTrue("Method {$method->getName()} should be public");

                // Check parameter count
                expect($implementationMethod->getNumberOfParameters())
                    ->toBe($method->getNumberOfParameters(), "Method {$method->getName()} should have same parameter count");
            }
        });
    });

    describe('media methods fluent interface compliance', function () {
        beforeEach(function () {
            // Create test files for interface compliance testing
            $this->testImageContent = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAI9jINSAAAAQElEQVR4nGNgAAQAAAABAAA=';
            $this->testImagePath = tempnam(sys_get_temp_dir(), 'test_image').'.png';
            file_put_contents($this->testImagePath, base64_decode($this->testImageContent));

            $this->testDocumentContent = 'This is a test document for interface compliance.';
            $this->testDocumentPath = tempnam(sys_get_temp_dir(), 'test_document').'.txt';
            file_put_contents($this->testDocumentPath, $this->testDocumentContent);
        });

        afterEach(function () {
            if (isset($this->testImagePath) && file_exists($this->testImagePath)) {
                unlink($this->testImagePath);
            }
            if (isset($this->testDocumentPath) && file_exists($this->testDocumentPath)) {
                unlink($this->testDocumentPath);
            }
        });

        test('image method returns static for fluent interface', function () {
            $transformer = app(PrismTransformer::class);
            $result = $transformer->image($this->testImagePath);

            expect($result)->toBe($transformer);
            expect($result)->toBeInstanceOf(PrismTransformerInterface::class);
        });

        test('document method returns static for fluent interface', function () {
            $transformer = app(PrismTransformer::class);
            $result = $transformer->document($this->testDocumentPath);

            expect($result)->toBe($transformer);
            expect($result)->toBeInstanceOf(PrismTransformerInterface::class);
        });

        test('media methods can be chained with other interface methods', function () {
            $transformer = app(PrismTransformer::class);

            // Test image method chaining
            $result = $transformer
                ->image($this->testImagePath)
                ->async()
                ->setContext(['test' => true]);

            expect($result)->toBe($transformer);
            expect($result)->toBeInstanceOf(PrismTransformerInterface::class);

            // Test document method chaining
            $transformer2 = app(PrismTransformer::class);
            $result2 = $transformer2
                ->document($this->testDocumentPath)
                ->async()
                ->setContext(['test' => true]);

            expect($result2)->toBe($transformer2);
            expect($result2)->toBeInstanceOf(PrismTransformerInterface::class);
        });

        test('media methods accept correct parameter types', function () {
            $transformer = app(PrismTransformer::class);

            // Test string path parameter
            $result1 = $transformer->image($this->testImagePath);
            expect($result1)->toBe($transformer);

            $result2 = app(PrismTransformer::class)->document($this->testDocumentPath);
            expect($result2)->toBeInstanceOf(PrismTransformer::class);

            // Test array options parameter
            $result3 = app(PrismTransformer::class)->image($this->testImagePath, ['inputType' => 'localPath']);
            expect($result3)->toBeInstanceOf(PrismTransformer::class);

            $result4 = app(PrismTransformer::class)->document($this->testDocumentPath, ['inputType' => 'localPath', 'title' => 'Test']);
            expect($result4)->toBeInstanceOf(PrismTransformer::class);
        });
    });

    describe('backward compatibility verification', function () {
        test('interface maintains all existing methods', function () {
            $existingMethods = ['text', 'url', 'async', 'using', 'setContext', 'transform'];

            foreach ($existingMethods as $methodName) {
                $interfaceReflection = new ReflectionClass(PrismTransformerInterface::class);
                expect($interfaceReflection->hasMethod($methodName))
                    ->toBeTrue("Interface should still have {$methodName} method");
            }
        });

        test('existing interface contract is unchanged', function () {
            $transformer = app(PrismTransformer::class);

            // All existing methods should still work exactly as before
            expect($transformer->text('test'))->toBe($transformer);
            expect($transformer->async())->toBe($transformer);
            expect($transformer->setContext(['test' => true]))->toBe($transformer);

            $closure = fn ($content) => \Droath\PrismTransformer\ValueObjects\TransformerResult::successful($content);
            expect($transformer->using($closure))->toBe($transformer);
        });
    });
});
