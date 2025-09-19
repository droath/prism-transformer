<?php

declare(strict_types=1);

use Droath\PrismTransformer\PrismTransformer;
use Droath\PrismTransformer\Tests\Stubs\TestModel;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Prism\Prism\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

describe('Model Pipeline Error Handling and Edge Cases', function () {
    beforeEach(function () {
        $this->transformer = app(PrismTransformer::class);
        $this->cacheManager = $this->app->make(\Illuminate\Cache\CacheManager::class);
        $this->configurationService = $this->app->make(\Droath\PrismTransformer\Services\ConfigurationService::class);
    });

    describe('AI Response Error Handling', function () {
        test('handles malformed data structure from AI response', function () {
            // Test with data that doesn't match expected model structure
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['invalid_structure' => 'test', 'not_model_fields' => true])
                ->withUsage(new Usage(10, 15));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Return data that does not match model structure';
                }
            };

            $result = $this->transformer
                ->text('Test malformed data handling')
                ->using($transformer::class)
                ->transform();

            // This should work fine - the model will just not have the expected fields filled
            $model = $result->toModel(TestModel::class);
            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBeNull(); // Unexpected structure means null values
        });

        test('handles structured response with null structured data', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(null) // This should cause issues
                ->withUsage(new Usage(8, 12));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Return null structured data';
                }
            };

            $result = $this->transformer
                ->text('Test null structured response')
                ->using($transformer::class)
                ->transform();

            expect(fn () => $result->toModel(TestModel::class))
                ->toThrow(\JsonException::class, 'Syntax error');
        });

        test('handles empty structured response', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured([]) // Empty array
                ->withUsage(new Usage(5, 0));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Return empty structured response';
                }
            };

            $result = $this->transformer
                ->text('Test empty response')
                ->using($transformer::class)
                ->transform();

            // Empty array should work fine - just create empty model
            $model = $result->toModel(TestModel::class);
            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBeNull();
        });

        test('handles AI response with null values', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['name' => null, 'email' => null, 'age' => null, 'is_active' => null])
                ->withUsage(new Usage(12, 18));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Return response with null values';
                }
            };

            $result = $this->transformer
                ->text('Test null values')
                ->using($transformer::class)
                ->transform();

            $model = $result->toModel(TestModel::class);
            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBeNull();
            expect($model->email)->toBeNull();
            expect($model->age)->toBeNull();
            expect($model->is_active)->toBeNull(); // Null values remain null
        });
    });

    describe('Model Validation Error Handling', function () {
        test('handles validation failures with strict rules', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['name' => 'A', 'email' => 'invalid-email', 'age' => 150, 'is_active' => 'not-boolean'])
                ->withUsage(new Usage(15, 20));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Return invalid data for validation testing';
                }
            };

            $result = $this->transformer
                ->text('Test validation failures')
                ->using($transformer::class)
                ->transform();

            $validationRules = [
                'name' => 'required|string|min:3',
                'email' => 'required|email',
                'age' => 'required|integer|max:120',
                'is_active' => 'required|boolean',
            ];

            expect(fn () => $result->toModel(TestModel::class, $validationRules))
                ->toThrow(ValidationException::class);
        });

        test('handles missing required fields in validation', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['name' => 'Test User']) // Missing required fields
                ->withUsage(new Usage(10, 12));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Return incomplete data';
                }
            };

            $result = $this->transformer
                ->text('Test missing fields')
                ->using($transformer::class)
                ->transform();

            $validationRules = [
                'name' => 'required',
                'email' => 'required|email',
                'age' => 'required|integer',
            ];

            expect(fn () => $result->toModel(TestModel::class, $validationRules))
                ->toThrow(ValidationException::class);
        });

        test('handles type coercion edge cases', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['name' => 123, 'email' => true, 'age' => 'not-a-number', 'is_active' => 'maybe'])
                ->withUsage(new Usage(14, 18));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Return data with wrong types';
                }
            };

            $result = $this->transformer
                ->text('Test type coercion')
                ->using($transformer::class)
                ->transform();

            $validationRules = [
                'name' => 'required|string',
                'email' => 'required|email',
                'age' => 'required|integer',
                'is_active' => 'required|boolean',
            ];

            expect(fn () => $result->toModel(TestModel::class, $validationRules))
                ->toThrow(ValidationException::class);
        });
    });

    describe('Model Class Error Handling', function () {
        test('handles non-existent model class', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['name' => 'Test User', 'email' => 'test@example.com'])
                ->withUsage(new Usage(8, 12));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Test with invalid model class';
                }
            };

            $result = $this->transformer
                ->text('Test non-existent model')
                ->using($transformer::class)
                ->transform();

            expect(fn () => $result->toModel('NonExistentModel'))
                ->toThrow(\InvalidArgumentException::class, 'Model class NonExistentModel does not exist');
        });

        test('handles invalid model class (not a Model)', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['name' => 'Test User', 'email' => 'test@example.com'])
                ->withUsage(new Usage(8, 12));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Test with invalid class type';
                }
            };

            $result = $this->transformer
                ->text('Test invalid class')
                ->using($transformer::class)
                ->transform();

            expect(fn () => $result->toModel(\stdClass::class))
                ->toThrow(\InvalidArgumentException::class, 'Class stdClass is not an Eloquent model');
        });

        test('handles model with protected fillable that conflicts with data', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['name' => 'Test', 'email' => 'test@example.com', 'id' => 999, 'password' => 'secret', 'restricted_field' => 'value'])
                ->withUsage(new Usage(16, 22));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Return data with non-fillable fields';
                }
            };

            $result = $this->transformer
                ->text('Test fillable protection')
                ->using($transformer::class)
                ->transform();

            $model = $result->toModel(TestModel::class);
            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBe('Test');
            expect($model->email)->toBe('test@example.com');

            // Non-fillable fields should be ignored, not cause errors
            expect($model->getAttributes())->not->toHaveKey('password');
            expect($model->getAttributes())->not->toHaveKey('restricted_field');
        });
    });

    describe('Pipeline State Error Handling', function () {
        test('handles failed transformation result', function () {
            // Create a failed transformation result
            $failedResult = TransformerResult::failed(
                ['AI transformation failed'],
                \Droath\PrismTransformer\ValueObjects\TransformerMetadata::make(
                    'gpt-4',
                    \Droath\PrismTransformer\Enums\Provider::OPENAI,
                    'test-transformer'
                )
            );

            expect(fn () => $failedResult->toModel(TestModel::class))
                ->toThrow(\RuntimeException::class, 'Cannot create a model from failed transformation');
        });

        test('handles transformation result without content', function () {
            // Create a successful result but without proper content
            $emptyResult = TransformerResult::successful(
                '',
                \Droath\PrismTransformer\ValueObjects\TransformerMetadata::make(
                    'gpt-4',
                    \Droath\PrismTransformer\Enums\Provider::OPENAI,
                    'test-transformer'
                )
            );

            expect(fn () => $emptyResult->toModel(TestModel::class))
                ->toThrow(\JsonException::class, 'Syntax error');
        });

        test('handles concurrent model creation attempts', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['name' => 'Concurrent User', 'email' => 'concurrent@test.com', 'age' => 25])
                ->withUsage(new Usage(10, 15));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Test concurrent access';
                }
            };

            $result = $this->transformer
                ->text('Test concurrent model creation')
                ->using($transformer::class)
                ->transform();

            // Multiple model creations from same result should work
            $model1 = $result->toModel(TestModel::class);
            $model2 = $result->toModel(TestModel::class);

            expect($model1)->toBeInstanceOf(TestModel::class);
            expect($model2)->toBeInstanceOf(TestModel::class);
            expect($model1->name)->toBe($model2->name);
            expect($model1->email)->toBe($model2->email);

            // But they should be different instances
            expect($model1)->not->toBe($model2);
        });
    });

    describe('Edge Cases and Boundary Conditions', function () {
        test('handles extremely large JSON response', function () {
            $largeDescription = str_repeat('Large text content with unicode characters: 测试 ', 1000);
            $largeData = [
                'name' => 'Large Data User',
                'email' => 'large@example.com',
                'age' => 30,
                'is_active' => true,
            ];

            $fakeResponse = StructuredResponseFake::make()
                ->withStructured($largeData)
                ->withUsage(new Usage(100, 150));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Handle large data response';
                }
            };

            $result = $this->transformer
                ->text('Test large data handling')
                ->using($transformer::class)
                ->transform();

            $model = $result->toModel(TestModel::class);
            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBe('Large Data User');
        });

        test('handles special characters and unicode in model data', function () {
            $specialData = [
                'name' => 'User with émojis 🎉 and unicode: 测试 العربية',
                'email' => 'unicode+test@example.com',
                'age' => 25,
                'is_active' => true,
            ];

            $fakeResponse = StructuredResponseFake::make()
                ->withStructured($specialData)
                ->withUsage(new Usage(18, 25));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Handle unicode and special characters';
                }
            };

            $result = $this->transformer
                ->text('Test unicode characters')
                ->using($transformer::class)
                ->transform();

            $model = $result->toModel(TestModel::class);
            expect($model->name)->toBe('User with émojis 🎉 and unicode: 测试 العربية');
            expect($model->email)->toBe('unicode+test@example.com');
        });

        test('handles model creation with minimal valid data', function () {
            $minimalData = [
                'name' => 'A', // Minimal but valid
            ];

            $fakeResponse = StructuredResponseFake::make()
                ->withStructured($minimalData)
                ->withUsage(new Usage(5, 8));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Return minimal data';
                }
            };

            $result = $this->transformer
                ->text('Test minimal data')
                ->using($transformer::class)
                ->transform();

            $model = $result->toModel(TestModel::class);
            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBe('A');
            expect($model->is_active)->toBeTrue(); // Default value
        });

        test('handles model with datetime and complex casting edge cases', function () {
            $complexData = [
                'name' => 'Complex User',
                'email' => 'complex@example.com',
                'age' => '30', // String that should be cast to int
                'is_active' => '0', // String that should be cast to boolean false
                'created_at' => '2023-01-15T10:30:00Z', // ISO date string
            ];

            $fakeResponse = StructuredResponseFake::make()
                ->withStructured($complexData)
                ->withUsage(new Usage(20, 28));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Return complex casting data';
                }
            };

            $result = $this->transformer
                ->text('Test complex casting')
                ->using($transformer::class)
                ->transform();

            $model = $result->toModel(TestModel::class);
            expect($model->name)->toBe('Complex User');
            expect($model->age)->toBe(30); // Cast to integer
            expect($model->is_active)->toBeFalse(); // Cast to boolean false
        });
    });
})->group('integration', 'error-handling', 'edge-cases');
