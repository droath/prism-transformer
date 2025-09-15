<?php

declare(strict_types=1);

use Droath\PrismTransformer\Concerns\ValidatesInput;
use Droath\PrismTransformer\Exceptions\InvalidInputException;

describe('ValidatesInput Trait', function () {
    beforeEach(function () {
        $this->class = new class
        {
            use ValidatesInput;

            protected function isValidInput(string $content): bool
            {
                return ! empty(trim($content));
            }
        };
    });

    test('trait can be used in a class', function () {
        $traits = class_uses($this->class);
        expect($traits)->toContain(ValidatesInput::class);
    });
});

describe('ValidatesInput Hook Integration', function () {
    beforeEach(function () {
        $this->validator = new class
        {
            use ValidatesInput;

            public array $calls = [];

            protected function isValidInput(string $content): bool
            {
                return strlen($content) >= 3;
            }

            protected function beforeTransform(string $content): void
            {
                $this->calls[] = 'beforeTransform called';
                // Note: Not calling parent since this is a standalone test class
                if (! $this->isValidInput($content)) {
                    throw new InvalidInputException('Content validation failed');
                }
            }

            public function testBeforeTransform(string $content): void
            {
                $this->beforeTransform($content);
            }
        };
    });

    test('beforeTransform hook validates input successfully', function () {
        $validContent = 'valid content';

        $this->validator->testBeforeTransform($validContent);

        expect($this->validator->calls)->toContain('beforeTransform called');
    });

    test('beforeTransform hook throws InvalidInputException for invalid input', function () {
        $invalidContent = 'hi';

        expect(fn () => $this->validator->testBeforeTransform($invalidContent))
            ->toThrow(InvalidInputException::class, 'Content validation failed');
    });
});

describe('ValidatesInput Abstract Method', function () {
    test('isValidInput must be implemented by concrete classes', function () {
        // This test verifies that the trait has an abstract method that must be implemented
        $reflection = new ReflectionMethod(ValidatesInput::class, 'isValidInput');
        expect($reflection->isAbstract())->toBeTrue();
    });
});

describe('ValidatesInput Error Handling', function () {
    beforeEach(function () {
        $this->validator = new class
        {
            use ValidatesInput;

            protected function isValidInput(string $content): bool
            {
                return false; // Always fails validation
            }

            public function testBeforeTransform(string $content): void
            {
                $this->beforeTransform($content);
            }
        };
    });

    test('throws InvalidInputException with proper message', function () {
        expect(fn () => $this->validator->testBeforeTransform('any content'))
            ->toThrow(InvalidInputException::class, 'Content validation failed');
    });
});
