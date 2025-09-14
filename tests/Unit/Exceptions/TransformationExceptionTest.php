<?php

declare(strict_types=1);

use Droath\PrismTransformer\Exceptions\TransformationException;
use Droath\PrismTransformer\Exceptions\InvalidInputException;
use Droath\PrismTransformer\Exceptions\FetchException;

describe('TransformationException Base Class', function () {
    test('extends base Exception class', function () {
        expect(class_exists(TransformationException::class))->toBeTrue();

        $reflection = new ReflectionClass(TransformationException::class);
        expect($reflection->getParentClass()->getName())->toBe(Exception::class);
    });

    test('can be instantiated with message only', function () {
        $exception = new TransformationException('Test message');

        expect($exception->getMessage())->toBe('Test message');
        expect($exception->getCode())->toBe(0);
        expect($exception->getPrevious())->toBeNull();
    });

    test('can be instantiated with message and code', function () {
        $exception = new TransformationException('Test message', 500);

        expect($exception->getMessage())->toBe('Test message');
        expect($exception->getCode())->toBe(500);
    });

    test('can be instantiated with previous exception', function () {
        $previous = new Exception('Previous exception');
        $exception = new TransformationException('Test message', 0, $previous);

        expect($exception->getMessage())->toBe('Test message');
        expect($exception->getPrevious())->toBe($previous);
    });

    test('has context property for transformation details', function () {
        $reflection = new ReflectionClass(TransformationException::class);

        // Check if context property exists or context methods exist
        $hasContextProperty = $reflection->hasProperty('context');
        $hasGetContextMethod = $reflection->hasMethod('getContext');

        expect($hasContextProperty || $hasGetContextMethod)->toBeTrue();
    });
});

describe('InvalidInputException', function () {
    test('extends TransformationException', function () {
        expect(class_exists(InvalidInputException::class))->toBeTrue();

        $reflection = new ReflectionClass(InvalidInputException::class);
        expect($reflection->getParentClass()->getName())->toBe(TransformationException::class);
    });

    test('can be instantiated with validation context', function () {
        $exception = new InvalidInputException('Invalid input provided');

        expect($exception->getMessage())->toBe('Invalid input provided');
        expect($exception)->toBeInstanceOf(TransformationException::class);
        expect($exception)->toBeInstanceOf(Exception::class);
    });

    test('maintains exception chain', function () {
        $original = new Exception('Original validation error');
        $exception = new InvalidInputException('Input validation failed', 0, $original);

        expect($exception->getPrevious())->toBe($original);
        expect($exception->getMessage())->toBe('Input validation failed');
    });
});

describe('FetchException', function () {
    test('extends TransformationException', function () {
        expect(class_exists(FetchException::class))->toBeTrue();

        $reflection = new ReflectionClass(FetchException::class);
        expect($reflection->getParentClass()->getName())->toBe(TransformationException::class);
    });

    test('can be instantiated with fetch error details', function () {
        $exception = new FetchException('Failed to fetch content');

        expect($exception->getMessage())->toBe('Failed to fetch content');
        expect($exception)->toBeInstanceOf(TransformationException::class);
        expect($exception)->toBeInstanceOf(Exception::class);
    });

    test('maintains exception chain from HTTP errors', function () {
        $httpError = new Exception('HTTP 404 Not Found');
        $exception = new FetchException('Content fetch failed', 0, $httpError);

        expect($exception->getPrevious())->toBe($httpError);
        expect($exception->getMessage())->toBe('Content fetch failed');
    });
});

describe('Exception Hierarchy Integration', function () {
    test('all custom exceptions can be caught as TransformationException', function () {
        $exceptions = [
            new TransformationException('Base exception'),
            new InvalidInputException('Input exception'),
            new FetchException('Fetch exception'),
        ];

        foreach ($exceptions as $exception) {
            expect($exception)->toBeInstanceOf(TransformationException::class);
        }
    });

    test('all custom exceptions can be caught as base Exception', function () {
        $exceptions = [
            new TransformationException('Base exception'),
            new InvalidInputException('Input exception'),
            new FetchException('Fetch exception'),
        ];

        foreach ($exceptions as $exception) {
            expect($exception)->toBeInstanceOf(Exception::class);
        }
    });

    test('exceptions maintain proper inheritance chain', function () {
        $invalidInput = new InvalidInputException('Test');
        $fetchError = new FetchException('Test');

        // InvalidInputException inheritance
        expect($invalidInput)->toBeInstanceOf(InvalidInputException::class);
        expect($invalidInput)->toBeInstanceOf(TransformationException::class);
        expect($invalidInput)->toBeInstanceOf(Exception::class);

        // FetchException inheritance
        expect($fetchError)->toBeInstanceOf(FetchException::class);
        expect($fetchError)->toBeInstanceOf(TransformationException::class);
        expect($fetchError)->toBeInstanceOf(Exception::class);
    });

    test('specific exception types can be caught individually', function () {
        try {
            throw new InvalidInputException('Invalid input');
        } catch (InvalidInputException $e) {
            expect($e->getMessage())->toBe('Invalid input');
        }

        try {
            throw new FetchException('Fetch failed');
        } catch (FetchException $e) {
            expect($e->getMessage())->toBe('Fetch failed');
        }
    });
});
