<?php

declare(strict_types=1);

use Droath\PrismTransformer\Traits\ProcessesResults;
use Droath\PrismTransformer\ValueObjects\TransformationResult;

describe('ProcessesResults Trait', function () {
    beforeEach(function () {
        $this->class = new class
        {
            use ProcessesResults;

            protected function processSuccessfulResult(TransformationResult $result): void
            {
                // Implementation for testing
            }
        };
    });

    test('trait can be used in a class', function () {
        $traits = class_uses($this->class);
        expect($traits)->toContain(ProcessesResults::class);
    });
});

describe('ProcessesResults Hook Integration', function () {
    beforeEach(function () {
        $this->processor = new class extends \Droath\PrismTransformer\Abstract\BaseTransformer
        {
            use ProcessesResults;

            public array $calls = [];

            public function prompt(): string
            {
                return 'Test prompt';
            }

            public function outputFormat(\Prism\Prism\Schema\ObjectSchema|\Illuminate\Database\Eloquent\Model $format): ?\Prism\Prism\Schema\ObjectSchema
            {
                return null;
            }

            protected function processSuccessfulResult(TransformationResult $result): void
            {
                $this->calls[] = 'processSuccessfulResult called';
            }

            public function testAfterTransform(TransformationResult $result): void
            {
                $this->afterTransform($result);
            }
        };
    });

    test('afterTransform hook processes successful results', function () {
        $successfulResult = new TransformationResult(
            status: 'completed',
            data: ['test' => 'data']
        );

        $this->processor->testAfterTransform($successfulResult);

        // The trait's afterTransform should automatically call processSuccessfulResult for successful results
        expect($this->processor->calls)->toContain('processSuccessfulResult called');
    });

    test('afterTransform hook skips processing for failed results', function () {
        $failedResult = new TransformationResult(
            status: 'failed',
            errors: ['Test error']
        );

        $this->processor->testAfterTransform($failedResult);

        // The trait should not call processSuccessfulResult for failed results
        expect($this->processor->calls)->not->toContain('processSuccessfulResult called');
    });
});

describe('ProcessesResults Result Processing', function () {
    beforeEach(function () {
        $this->processor = new class extends \Droath\PrismTransformer\Abstract\BaseTransformer
        {
            use ProcessesResults;

            public ?TransformationResult $lastProcessedResult = null;

            public function prompt(): string
            {
                return 'Test prompt';
            }

            public function outputFormat(\Prism\Prism\Schema\ObjectSchema|\Illuminate\Database\Eloquent\Model $format): ?\Prism\Prism\Schema\ObjectSchema
            {
                return null;
            }

            protected function processSuccessfulResult(TransformationResult $result): void
            {
                $this->lastProcessedResult = $result;
            }

            public function testAfterTransform(TransformationResult $result): void
            {
                $this->afterTransform($result);
            }
        };
    });

    test('processSuccessfulResult receives the correct result', function () {
        $result = new TransformationResult(
            status: 'completed',
            data: ['key' => 'value'],
            metadata: ['test' => true]
        );

        $this->processor->testAfterTransform($result);

        expect($this->processor->lastProcessedResult)->toBe($result);
    });

    test('processSuccessfulResult is not called for results with errors', function () {
        $result = new TransformationResult(
            status: 'completed',
            data: ['key' => 'value'],
            errors: ['Some error']
        );

        $this->processor->testAfterTransform($result);

        expect($this->processor->lastProcessedResult)->toBeNull();
    });
});
