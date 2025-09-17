<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Tests\Unit\ValueObjects;

use Droath\PrismTransformer\Tests\Stubs\TestModel;
use Droath\PrismTransformer\Tests\Stubs\ProfileModel;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\ValueObjects\TransformerMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

describe('TransformerResult toModel() method', function () {
    describe('successful model hydration', function () {
        test('creates model from valid JSON data', function () {
            $jsonData = json_encode([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'age' => 30,
                'is_active' => true,
            ]);

            $result = TransformerResult::successful($jsonData);
            $model = $result->toModel(TestModel::class);

            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBe('John Doe');
            expect($model->email)->toBe('john@example.com');
            expect($model->age)->toBe(30);
            expect($model->is_active)->toBe(true);
        });

        test('creates model with partial data using fillable attributes', function () {
            $jsonData = json_encode([
                'name' => 'Jane Smith',
                'age' => 25,
                'extra_field' => 'ignored', // Should be ignored due to fillable
            ]);

            $result = TransformerResult::successful($jsonData);
            $model = $result->toModel(TestModel::class);

            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBe('Jane Smith');
            expect($model->age)->toBe(25);
            expect($model->is_active)->toBe(true); // Default value
            expect($model->email)->toBeNull();
            expect(isset($model->extra_field))->toBeFalse();
        });

        test('applies model casts correctly', function () {
            $jsonData = json_encode([
                'name' => 'Test User',
                'age' => '42', // String that should be cast to integer
                'is_active' => 'true', // String that should be cast to boolean
            ]);

            $result = TransformerResult::successful($jsonData);
            $model = $result->toModel(TestModel::class);

            expect($model->age)->toBe(42);
            expect($model->age)->toBeInt();
            expect($model->is_active)->toBe(true);
            expect($model->is_active)->toBeBool();
        });

        test('handles different model types', function () {
            $profileData = json_encode([
                'bio' => 'Software Developer',
                'website' => 'https://example.com',
            ]);

            $result = TransformerResult::successful($profileData);
            $model = $result->toModel(ProfileModel::class);

            expect($model)->toBeInstanceOf(ProfileModel::class);
            expect($model->bio)->toBe('Software Developer');
            expect($model->website)->toBe('https://example.com');
        });

        test('creates model without validation rules', function () {
            $jsonData = json_encode(['name' => 'No Validation']);

            $result = TransformerResult::successful($jsonData);
            $model = $result->toModel(TestModel::class);

            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBe('No Validation');
        });
    });

    describe('validation integration', function () {
        test('applies validation rules and passes', function () {
            $jsonData = json_encode([
                'name' => 'Valid User',
                'email' => 'valid@example.com',
                'age' => 25,
            ]);

            $validationRules = [
                'name' => 'required|string|min:3',
                'email' => 'required|email',
                'age' => 'required|integer|min:18',
            ];

            $result = TransformerResult::successful($jsonData);
            $model = $result->toModel(TestModel::class, $validationRules);

            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBe('Valid User');
            expect($model->email)->toBe('valid@example.com');
            expect($model->age)->toBe(25);
        });

        test('throws validation exception for invalid data', function () {
            $jsonData = json_encode([
                'name' => 'X', // Too short
                'email' => 'not-an-email',
                'age' => 15, // Too young
            ]);

            $validationRules = [
                'name' => 'required|string|min:3',
                'email' => 'required|email',
                'age' => 'required|integer|min:18',
            ];

            $result = TransformerResult::successful($jsonData);

            expect(fn () => $result->toModel(TestModel::class, $validationRules))
                ->toThrow(ValidationException::class);
        });

        test('validates only specified fields', function () {
            $jsonData = json_encode([
                'name' => 'Valid Name',
                'email' => 'invalid-email', // Invalid but not validated
                'age' => 30,
            ]);

            $validationRules = [
                'name' => 'required|string|min:3',
                'age' => 'required|integer|min:18',
                // email not included in validation
            ];

            $result = TransformerResult::successful($jsonData);
            $model = $result->toModel(TestModel::class, $validationRules);

            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBe('Valid Name');
            expect($model->email)->toBe('invalid-email');
            expect($model->age)->toBe(30);
        });
    });

    describe('error handling', function () {
        test('throws exception for failed transformation result', function () {
            $result = TransformerResult::failed(['Transformation failed']);

            expect(fn () => $result->toModel(TestModel::class))
                ->toThrow(\RuntimeException::class, 'Cannot create a model from failed transformation');
        });

        test('throws exception for invalid JSON data', function () {
            $result = TransformerResult::successful('invalid json data');

            expect(fn () => $result->toModel(TestModel::class))
                ->toThrow(\JsonException::class);
        });

        test('throws exception for non-object JSON', function () {
            $result = TransformerResult::successful('"string value"');

            expect(fn () => $result->toModel(TestModel::class))
                ->toThrow(\InvalidArgumentException::class, 'JSON must represent an object');
        });

        test('throws exception for non-existent model class', function () {
            $jsonData = json_encode(['name' => 'Test']);
            $result = TransformerResult::successful($jsonData);

            expect(fn () => $result->toModel('NonExistentModel'))
                ->toThrow(\InvalidArgumentException::class, 'Model class NonExistentModel does not exist');
        });

        test('throws exception for class that is not a model', function () {
            $jsonData = json_encode(['name' => 'Test']);
            $result = TransformerResult::successful($jsonData);

            expect(fn () => $result->toModel(\stdClass::class))
                ->toThrow(\InvalidArgumentException::class, 'Class stdClass is not an Eloquent model');
        });

        test('handles null data gracefully', function () {
            $result = new TransformerResult(
                TransformerResult::STATUS_COMPLETED,
                null
            );

            expect(fn () => $result->toModel(TestModel::class))
                ->toThrow(\RuntimeException::class, 'No content available to create a model');
        });
    });

    describe('edge cases and special scenarios', function () {
        test('handles empty JSON object', function () {
            $result = TransformerResult::successful('{}');
            $model = $result->toModel(TestModel::class);

            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBeNull();
            expect($model->is_active)->toBe(true); // Default value
        });

        test('handles nested JSON data by flattening appropriately', function () {
            $jsonData = json_encode([
                'name' => 'User with nested data',
                'profile' => [
                    'bio' => 'This should be ignored for TestModel',
                ],
                'age' => 28,
            ]);

            $result = TransformerResult::successful($jsonData);
            $model = $result->toModel(TestModel::class);

            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBe('User with nested data');
            expect($model->age)->toBe(28);
            expect(isset($model->profile))->toBeFalse();
        });

        test('handles array values in JSON appropriately', function () {
            $jsonData = json_encode([
                'name' => 'Array User',
                'tags' => ['tag1', 'tag2'], // Array value should be ignored for TestModel
                'age' => 35,
            ]);

            $result = TransformerResult::successful($jsonData);
            $model = $result->toModel(TestModel::class);

            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBe('Array User');
            expect($model->age)->toBe(35);
            expect(isset($model->tags))->toBeFalse();
        });

        test('preserves metadata from original result', function () {
            $metadata = TransformerMetadata::make(
                model: 'test-model',
                provider: \Droath\PrismTransformer\Enums\Provider::OPENAI,
                transformerClass: TestModel::class
            );

            $jsonData = json_encode(['name' => 'Test User']);
            $result = TransformerResult::successful($jsonData, $metadata);
            $model = $result->toModel(TestModel::class);

            expect($model)->toBeInstanceOf(TestModel::class);
            expect($result->getMetadata())->toBe($metadata);
        });
    });

    describe('type safety and return types', function () {
        test('returns exact model type specified', function () {
            $jsonData = json_encode(['name' => 'Type Test']);

            $result = TransformerResult::successful($jsonData);
            $testModel = $result->toModel(TestModel::class);
            $profileModel = $result->toModel(ProfileModel::class);

            expect($testModel)->toBeInstanceOf(TestModel::class);
            expect($testModel)->not->toBeInstanceOf(ProfileModel::class);
            expect($profileModel)->toBeInstanceOf(ProfileModel::class);
            expect($profileModel)->not->toBeInstanceOf(TestModel::class);
        });

        test('returns model that extends base Model class', function () {
            $jsonData = json_encode(['name' => 'Model Test']);
            $result = TransformerResult::successful($jsonData);
            $model = $result->toModel(TestModel::class);

            expect($model)->toBeInstanceOf(Model::class);
            expect($model)->toBeInstanceOf(TestModel::class);
        });
    });
});
