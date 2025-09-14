<?php

declare(strict_types=1);

use Droath\PrismTransformer\TransformationResult;

describe('TransformationResult', function () {
    test('can be instantiated with all parameters', function () {
        $result = new TransformationResult(
            status: 'completed',
            data: ['key' => 'value'],
            metadata: ['model' => 'gpt-4', 'tokens' => 100],
            errors: []
        );

        expect($result->status)->toBe('completed');
        expect($result->data)->toBe(['key' => 'value']);
        expect($result->metadata)->toBe(['model' => 'gpt-4', 'tokens' => 100]);
        expect($result->errors)->toBe([]);
    });

    test('can be instantiated with minimal parameters', function () {
        $result = new TransformationResult(status: 'completed');

        expect($result->status)->toBe('completed');
        expect($result->data)->toBeNull();
        expect($result->metadata)->toBe([]);
        expect($result->errors)->toBe([]);
    });
});

describe('TransformationResult Status Checks', function () {
    test('isSuccessful returns true for completed status with no errors', function () {
        $result = new TransformationResult(status: 'completed');
        expect($result->isSuccessful())->toBeTrue();
    });

    test('isSuccessful returns false for failed status', function () {
        $result = new TransformationResult(status: 'failed');
        expect($result->isSuccessful())->toBeFalse();
    });

    test('isSuccessful returns false when errors are present', function () {
        $result = new TransformationResult(
            status: 'completed',
            errors: ['Some error occurred']
        );
        expect($result->isSuccessful())->toBeFalse();
    });

    test('isFailed returns true for failed status', function () {
        $result = new TransformationResult(status: 'failed');
        expect($result->isFailed())->toBeTrue();
    });

    test('isFailed returns true when errors are present', function () {
        $result = new TransformationResult(
            status: 'completed',
            errors: ['Some error occurred']
        );
        expect($result->isFailed())->toBeTrue();
    });

    test('isFailed returns false for successful result', function () {
        $result = new TransformationResult(status: 'completed');
        expect($result->isFailed())->toBeFalse();
    });
});

describe('TransformationResult Error Handling', function () {
    test('getFirstError returns first error when errors exist', function () {
        $result = new TransformationResult(
            status: 'failed',
            errors: ['First error', 'Second error']
        );

        expect($result->getFirstError())->toBe('First error');
    });

    test('getFirstError returns null when no errors exist', function () {
        $result = new TransformationResult(status: 'completed');

        expect($result->getFirstError())->toBeNull();
    });
});

describe('TransformationResult Array Conversion', function () {
    test('toArray returns all properties', function () {
        $result = new TransformationResult(
            status: 'completed',
            data: ['transformed' => 'data'],
            metadata: ['tokens' => 50],
            errors: []
        );

        expect($result->toArray())->toBe([
            'status' => 'completed',
            'data' => ['transformed' => 'data'],
            'metadata' => ['tokens' => 50],
            'errors' => [],
        ]);
    });
});

describe('TransformationResult Static Factory Methods', function () {
    test('successful creates a completed result with data', function () {
        $data = ['key' => 'value'];
        $metadata = ['model' => 'gpt-4'];

        $result = TransformationResult::successful($data, $metadata);

        expect($result->status)->toBe('completed');
        expect($result->data)->toBe($data);
        expect($result->metadata)->toBe($metadata);
        expect($result->errors)->toBe([]);
        expect($result->isSuccessful())->toBeTrue();
    });

    test('successful can be created without metadata', function () {
        $data = ['key' => 'value'];

        $result = TransformationResult::successful($data);

        expect($result->status)->toBe('completed');
        expect($result->data)->toBe($data);
        expect($result->metadata)->toBe([]);
        expect($result->errors)->toBe([]);
    });

    test('failed creates a failed result with errors', function () {
        $errors = ['Error message'];
        $metadata = ['attempted_model' => 'gpt-4'];

        $result = TransformationResult::failed($errors, $metadata);

        expect($result->status)->toBe('failed');
        expect($result->data)->toBeNull();
        expect($result->metadata)->toBe($metadata);
        expect($result->errors)->toBe($errors);
        expect($result->isFailed())->toBeTrue();
    });

    test('failed can be created without metadata', function () {
        $errors = ['Error message'];

        $result = TransformationResult::failed($errors);

        expect($result->status)->toBe('failed');
        expect($result->data)->toBeNull();
        expect($result->metadata)->toBe([]);
        expect($result->errors)->toBe($errors);
    });
});
