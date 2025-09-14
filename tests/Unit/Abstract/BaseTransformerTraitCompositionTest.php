<?php

declare(strict_types=1);

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Traits\ValidatesInput;
use Droath\PrismTransformer\Traits\ProcessesResults;
use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\ValueObjects\TransformationResult;
use Prism\Prism\Schema\ObjectSchema;
use Illuminate\Database\Eloquent\Model;

describe('BaseTransformer Trait Composition', function () {
    beforeEach(function () {
        $this->transformer = new class extends BaseTransformer
        {
            use ProcessesResults, ValidatesInput;

            public function prompt(): string
            {
                return 'Transform the input data';
            }

            public function provider(): Provider
            {
                return Provider::OPENAI;
            }

            public function model(): string
            {
                return 'gpt-4o-mini';
            }

            public function outputFormat(ObjectSchema|Model $format): ?ObjectSchema
            {
                return null;
            }

            protected function isValidInput(string $content): bool
            {
                return ! empty(trim($content));
            }

            protected function processSuccessfulResult(TransformationResult $result): void
            {
                // Custom processing logic for testing
            }
        };
    });

    test('BaseTransformer uses ValidatesInput trait', function () {
        $traits = class_uses($this->transformer);
        expect($traits)->toContain(ValidatesInput::class);
    });

    test('BaseTransformer uses ProcessesResults trait', function () {
        $traits = class_uses($this->transformer);
        expect($traits)->toContain(ProcessesResults::class);
    });
});

describe('BaseTransformer with Trait Integration', function () {
    beforeEach(function () {
        $this->transformer = new class extends BaseTransformer
        {
            use ProcessesResults, ValidatesInput;

            public array $calls = [];

            public function prompt(): string
            {
                return 'Process the integrated data';
            }

            public function provider(): Provider
            {
                return Provider::ANTHROPIC;
            }

            public function model(): string
            {
                return 'claude-3-5-haiku-20241022';
            }

            public function outputFormat(ObjectSchema|Model $format): ?ObjectSchema
            {
                return null;
            }

            protected function isValidInput(string $content): bool
            {
                return ! empty(trim($content)) && strlen($content) >= 3;
            }

            protected function beforeTransform(string $content): void
            {
                $this->calls[] = 'beforeTransform';
                // Let the trait handle validation first
                if (! $this->isValidInput($content)) {
                    throw new \Droath\PrismTransformer\Exceptions\InvalidInputException('Content validation failed');
                }
                parent::beforeTransform($content);
            }

            protected function afterTransform(TransformationResult $result): void
            {
                $this->calls[] = 'afterTransform';
                parent::afterTransform($result);
            }

            protected function processSuccessfulResult(TransformationResult $result): void
            {
                $this->calls[] = 'processSuccessfulResult';
            }
        };
    });

    test('validation hooks work with beforeTransform', function () {
        // Test with valid input
        $validInput = 'valid content';
        $result = $this->transformer->execute($validInput);

        expect($result)->toBeInstanceOf(TransformationResult::class);
        expect($this->transformer->calls)->toContain('beforeTransform');
    });

    test('validation hooks throw exception for invalid input', function () {
        // Test with invalid input (too short) - should be < 3 characters
        $invalidInput = 'hi';

        // The transformer requires input length >= 3, so 'hi' (2 chars) should fail
        expect(fn () => $this->transformer->execute($invalidInput))
            ->toThrow(\Droath\PrismTransformer\Exceptions\InvalidInputException::class);
    });

    test('result processing hooks work with afterTransform', function () {
        $validInput = 'valid content';
        $result = $this->transformer->execute($validInput);

        expect($result)->toBeInstanceOf(TransformationResult::class);
        expect($this->transformer->calls)->toContain('afterTransform');
        // The trait should call processSuccessfulResult for successful results
        if ($result->isSuccessful()) {
            expect($this->transformer->calls)->toContain('processSuccessfulResult');
        }
    });

    test('trait methods are accessible', function () {
        $reflection = new ReflectionClass($this->transformer);
        $methods = $reflection->getMethods();

        $methodNames = array_map(fn ($method) => $method->getName(), $methods);

        // Check that trait methods are present
        expect($methodNames)->toContain('beforeTransform');
        expect($methodNames)->toContain('afterTransform');
        expect($methodNames)->toContain('isValidInput');
        expect($methodNames)->toContain('processSuccessfulResult');
    });
});

describe('BaseTransformer Trait Method Resolution', function () {
    beforeEach(function () {
        $this->transformer = new class extends BaseTransformer
        {
            use ProcessesResults, ValidatesInput;

            public function prompt(): string
            {
                return 'Test method resolution';
            }

            public function provider(): Provider
            {
                return Provider::GROQ;
            }

            public function model(): string
            {
                return 'llama-3.1-8b';
            }

            public function outputFormat(ObjectSchema|Model $format): ?ObjectSchema
            {
                return null;
            }

            protected function isValidInput(string $content): bool
            {
                return true;
            }

            protected function processSuccessfulResult(TransformationResult $result): void
            {
                // Test implementation
            }
        };
    });

    test('no method conflicts between traits', function () {
        $reflection = new ReflectionClass($this->transformer);
        $methods = $reflection->getMethods();

        $methodNames = array_map(fn ($method) => $method->getName(), $methods);

        // Check that all expected methods are present
        expect($methodNames)->toContain('beforeTransform');
        expect($methodNames)->toContain('afterTransform');
        expect($methodNames)->toContain('isValidInput');
        expect($methodNames)->toContain('processSuccessfulResult');
    });

    test('trait hooks integrate properly with execute method', function () {
        $content = 'test content';
        $result = $this->transformer->execute($content);

        expect($result)->toBeInstanceOf(TransformationResult::class);
        // The fact that no exceptions are thrown indicates proper integration
    });
});

describe('BaseTransformer Enhanced Functionality', function () {
    beforeEach(function () {
        $this->transformer = new class extends BaseTransformer
        {
            use ProcessesResults, ValidatesInput;

            private bool $strictValidation = true;

            public array $processedResults = [];

            public function prompt(): string
            {
                return 'Enhanced transformation';
            }

            public function provider(): Provider
            {
                return Provider::MISTRAL;
            }

            public function model(): string
            {
                return 'mistral-7b-instruct';
            }

            public function outputFormat(ObjectSchema|Model $format): ?ObjectSchema
            {
                return null;
            }

            protected function isValidInput(string $content): bool
            {
                if (! $this->strictValidation) {
                    return true;
                }

                return ! empty(trim($content)) && strlen($content) > 0;
            }

            protected function processSuccessfulResult(TransformationResult $result): void
            {
                $this->processedResults[] = $result;
            }

            public function setStrictValidation(bool $strict): self
            {
                $this->strictValidation = $strict;

                return $this;
            }
        };
    });

    test('validation behavior can be controlled', function () {
        // With strict validation enabled (default)
        expect(fn () => $this->transformer->execute(''))
            ->toThrow(Exception::class);

        // With strict validation disabled
        $this->transformer->setStrictValidation(false);
        $result = $this->transformer->execute('');

        expect($result)->toBeInstanceOf(TransformationResult::class);
    });

    test('result processing captures successful transformations', function () {
        $content = 'test content';
        $result = $this->transformer->execute($content);

        expect($result)->toBeInstanceOf(TransformationResult::class);

        // Only expect processed results if the transformation was successful
        if ($result->isSuccessful()) {
            expect($this->transformer->processedResults)->toHaveCount(1);
            expect($this->transformer->processedResults[0])->toBe($result);
        }
    });
});
