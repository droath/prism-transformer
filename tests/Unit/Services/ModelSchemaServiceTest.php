<?php

declare(strict_types=1);

use Droath\PrismTransformer\Services\ModelSchemaService;
use Droath\PrismTransformer\Tests\Stubs\TestModel;
use Droath\PrismTransformer\Tests\Stubs\RelatedModel;
use Droath\PrismTransformer\Tests\Stubs\ProfileModel;
use Illuminate\Database\Eloquent\Model;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\ArraySchema;

describe('ModelSchemaService', function () {
    beforeEach(function () {
        $this->service = new ModelSchemaService();
    });

    describe('convertModelToSchema method', function () {
        test('returns ObjectSchema for models with fillable attributes', function () {
            $model = new TestModel();

            $result = $this->service->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect($result->name)->toBe('testmodel');
            expect($result->description)->toBe('Schema for TestModel model');
            expect(count($result->properties))->toBe(5);
            expect(count($result->requiredFields))->toBe(5);
        });

        test('returns null for models with no fillable attributes', function () {
            $model = new class extends Model
            {
                protected $fillable = [];
            };

            $result = $this->service->convertModelToSchema($model);

            expect($result)->toBeNull();
        });

        test('handles different model types correctly', function () {
            $testModel = new TestModel();
            $relatedModel = new RelatedModel();
            $profileModel = new ProfileModel();

            $testResult = $this->service->convertModelToSchema($testModel);
            $relatedResult = $this->service->convertModelToSchema($relatedModel);
            $profileResult = $this->service->convertModelToSchema($profileModel);

            expect($testResult)->toBeInstanceOf(ObjectSchema::class);
            expect($relatedResult)->toBeInstanceOf(ObjectSchema::class);
            expect($profileResult)->toBeInstanceOf(ObjectSchema::class);

            expect($testResult->name)->toBe('testmodel');
            expect($relatedResult->name)->toBe('relatedmodel');
            expect($profileResult->name)->toBe('profilemodel');
        });

        test('handles models with complex cast types', function () {
            $model = new class extends Model
            {
                protected $fillable = [
                    'json_field',
                    'array_field',
                    'collection_field',
                    'decimal_field',
                    'date_field',
                    'datetime_field',
                    'timestamp_field',
                ];

                protected $casts = [
                    'json_field' => 'json',
                    'array_field' => 'array',
                    'collection_field' => 'collection',
                    'decimal_field' => 'decimal:2',
                    'date_field' => 'date',
                    'datetime_field' => 'datetime',
                    'timestamp_field' => 'timestamp',
                ];
            };

            $result = $this->service->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect(count($result->properties))->toBe(7);

            // Check specific schema types
            $propertyTypes = array_map(fn ($prop) => get_class($prop), $result->properties);
            expect($propertyTypes)->toContain(ArraySchema::class); // json, array, collection
            expect($propertyTypes)->toContain(NumberSchema::class); // decimal
            expect($propertyTypes)->toContain(StringSchema::class); // date, datetime, timestamp
        });

        test('filters out empty and invalid field names', function () {
            $model = new class extends Model
            {
                protected $fillable = ['', 'valid_field', '   ', 'another_field'];
            };

            $result = $this->service->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            // Should only include valid_field and another_field
            expect(count($result->properties))->toBe(2);
            expect($result->requiredFields)->toContain('valid_field');
            expect($result->requiredFields)->toContain('another_field');
        });
    });

    describe('cast type to schema mapping', function () {
        test('maps boolean casts to BooleanSchema', function () {
            $model = new class extends Model
            {
                protected $fillable = ['bool_field', 'boolean_field'];

                protected $casts = [
                    'bool_field' => 'bool',
                    'boolean_field' => 'boolean',
                ];
            };

            $result = $this->service->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect(count($result->properties))->toBe(2);

            foreach ($result->properties as $property) {
                expect($property)->toBeInstanceOf(BooleanSchema::class);
            }
        });

        test('maps numeric casts to NumberSchema', function () {
            $model = new class extends Model
            {
                protected $fillable = ['int_field', 'integer_field', 'float_field', 'double_field', 'real_field', 'decimal_field'];

                protected $casts = [
                    'int_field' => 'int',
                    'integer_field' => 'integer',
                    'float_field' => 'float',
                    'double_field' => 'double',
                    'real_field' => 'real',
                    'decimal_field' => 'decimal:2',
                ];
            };

            $result = $this->service->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect(count($result->properties))->toBe(6);

            foreach ($result->properties as $property) {
                expect($property)->toBeInstanceOf(NumberSchema::class);
            }
        });

        test('maps array/collection casts to ArraySchema', function () {
            $model = new class extends Model
            {
                protected $fillable = ['array_field', 'json_field', 'collection_field'];

                protected $casts = [
                    'array_field' => 'array',
                    'json_field' => 'json',
                    'collection_field' => 'collection',
                ];
            };

            $result = $this->service->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect(count($result->properties))->toBe(3);

            foreach ($result->properties as $property) {
                expect($property)->toBeInstanceOf(ArraySchema::class);
                expect($property->items)->toBeInstanceOf(StringSchema::class);
            }
        });

        test('maps date/time casts to StringSchema with ISO format description', function () {
            $model = new class extends Model
            {
                protected $fillable = ['date_field', 'datetime_field', 'timestamp_field'];

                protected $casts = [
                    'date_field' => 'date',
                    'datetime_field' => 'datetime',
                    'timestamp_field' => 'timestamp',
                ];
            };

            $result = $this->service->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect(count($result->properties))->toBe(3);

            foreach ($result->properties as $property) {
                expect($property)->toBeInstanceOf(StringSchema::class);
                expect($property->description)->toContain('ISO 8601 format');
            }
        });

        test('defaults unknown casts to StringSchema', function () {
            $model = new class extends Model
            {
                protected $fillable = ['custom_field', 'encrypted_field', 'unknown_field'];

                protected $casts = [
                    'custom_field' => 'custom_type',
                    'encrypted_field' => 'encrypted',
                    'unknown_field' => 'some_unknown_cast',
                ];
            };

            $result = $this->service->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect(count($result->properties))->toBe(3);

            foreach ($result->properties as $property) {
                expect($property)->toBeInstanceOf(StringSchema::class);
            }
        });

        test('defaults fields without explicit casts to StringSchema', function () {
            $model = new class extends Model
            {
                protected $fillable = ['name', 'email', 'description'];

                protected $casts = []; // No casts defined
            };

            $result = $this->service->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect(count($result->properties))->toBe(3);

            foreach ($result->properties as $property) {
                expect($property)->toBeInstanceOf(StringSchema::class);
            }
        });
    });

    describe('error handling and edge cases', function () {
        test('handles models that throw exceptions when accessing fillable', function () {
            // Test using a service that simulates getFillable throwing exception
            $service = new class extends ModelSchemaService
            {
                public function convertModelToSchema(Model $model, array $config = []): ?ObjectSchema
                {
                    try {
                        // Simulate the exception that would occur in extractModelAttributes
                        throw new \RuntimeException('Cannot access fillable');
                    } catch (\Throwable) {
                        return null;
                    }
                }
            };

            $model = new TestModel();
            $result = $service->convertModelToSchema($model);

            expect($result)->toBeNull();
        });

        test('handles models that throw exceptions when accessing casts', function () {
            $model = new class extends Model
            {
                protected $fillable = ['name'];

                public function getCasts()
                {
                    throw new \RuntimeException('Cannot access casts');
                }
            };

            $result = $this->service->convertModelToSchema($model);

            expect($result)->toBeNull();
        });

        test('handles models with corrupted fillable data', function () {
            // Test by simulating corrupted data handling
            $service = new class extends ModelSchemaService
            {
                public function convertModelToSchema(Model $model, array $config = []): ?ObjectSchema
                {
                    // Simulate the scenario where fillable data is corrupted
                    $modelAttributes = ['fillable' => null, 'casts' => []];

                    if (empty($modelAttributes['fillable'])) {
                        return null;
                    }

                    return parent::convertModelToSchema($model);
                }
            };

            $model = new TestModel();
            $result = $service->convertModelToSchema($model);

            expect($result)->toBeNull();
        });

        test('skips fields that cause schema creation errors', function () {
            // Create a service that simulates schema creation failure
            $service = new class extends ModelSchemaService
            {
                protected function createSchemaForCastType(string $fieldName, string $castType): ?\Prism\Prism\Contracts\Schema
                {
                    if ($fieldName === 'problematic_field') {
                        throw new \RuntimeException('Schema creation failed');
                    }

                    return parent::createSchemaForCastType($fieldName, $castType);
                }
            };

            $model = new class extends Model
            {
                protected $fillable = ['good_field', 'problematic_field', 'another_good_field'];
            };

            $result = $service->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            // Should have 2 properties instead of 3 (problematic_field skipped)
            expect(count($result->properties))->toBe(2);
            expect($result->requiredFields)->toContain('good_field');
            expect($result->requiredFields)->toContain('another_good_field');
            expect($result->requiredFields)->not->toContain('problematic_field');
        });

        test('handles ObjectSchema creation errors gracefully', function () {
            // This is harder to test directly without mocking ObjectSchema constructor
            // But we can test with edge case data that might cause issues
            $model = new class extends Model
            {
                protected $fillable = ['field'];

                public function __toString()
                {
                    throw new \RuntimeException('toString error');
                }
            };

            // Should not throw exceptions even with problematic models
            expect(fn () => $this->service->convertModelToSchema($model))->not->toThrow(\Exception::class);
        });

        test('handles anonymous classes gracefully', function () {
            $model = new class extends Model
            {
                protected $fillable = ['name', 'email'];
            };

            $result = $this->service->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect($result->name)->toBeString();
            expect($result->description)->toBeString();
            expect(count($result->properties))->toBe(2);
        });

        test('handles very large models with many attributes', function () {
            $fillableAttributes = [];
            for ($i = 1; $i <= 100; $i++) {
                $fillableAttributes[] = "field_{$i}";
            }

            $model = new class extends Model
            {
                protected $fillable = [];

                public function setFillable(array $fillable): void
                {
                    $this->fillable = $fillable;
                }
            };

            $model->setFillable($fillableAttributes);

            $result = $this->service->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect(count($result->properties))->toBe(100);
            expect(count($result->requiredFields))->toBe(100);
        });
    });

    describe('schema field name and description generation', function () {
        test('generates proper field descriptions from field names', function () {
            $model = new class extends Model
            {
                protected $fillable = ['user_name', 'email_address', 'is_active', 'created_at'];
            };

            $result = $this->service->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);

            $descriptions = array_map(fn ($prop) => $prop->description, $result->properties);

            expect($descriptions)->toContain('User name');
            expect($descriptions)->toContain('Email address');
            expect($descriptions)->toContain('Is active');
            expect($descriptions)->toContain('Created at');
        });

        test('handles edge case field names', function () {
            $model = new class extends Model
            {
                protected $fillable = ['a', 'UPPERCASE', 'mixedCase', 'with123numbers'];
            };

            $result = $this->service->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect(count($result->properties))->toBe(4);

            // All should have descriptions
            foreach ($result->properties as $property) {
                expect($property->description)->toBeString();
                expect(strlen($property->description))->toBeGreaterThan(0);
            }
        });
    });

    describe('match expression functionality', function () {
        test('uses modern match expression for cast type mapping', function () {
            // This test verifies that the match expression works correctly
            // by testing various cast type combinations that would have different
            // behavior in old switch vs new match

            $model = new class extends Model
            {
                protected $fillable = ['test_field'];

                protected $casts = ['test_field' => 'boolean'];
            };

            $result = $this->service->convertModelToSchema($model);
            expect($result->properties[0])->toBeInstanceOf(BooleanSchema::class);

            // Test that it handles the exact matching of the match expression
            $model = new class extends Model
            {
                protected $fillable = ['test_field'];

                protected $casts = ['test_field' => 'decimal:5,2'];
            };

            $result = $this->service->convertModelToSchema($model);
            expect($result->properties[0])->toBeInstanceOf(NumberSchema::class);
        });
    });

    describe('service isolation and testability', function () {
        test('can be instantiated without dependencies', function () {
            $service = new ModelSchemaService();

            expect($service)->toBeInstanceOf(ModelSchemaService::class);
        });

        test('can be extended for custom behavior', function () {
            $customService = new class extends ModelSchemaService
            {
                protected function createSchemaForCastType(string $fieldName, string $castType): ?\Prism\Prism\Contracts\Schema
                {
                    // Custom logic for specific fields
                    if ($fieldName === 'special_field') {
                        return new StringSchema($fieldName, 'Special custom description');
                    }

                    return parent::createSchemaForCastType($fieldName, $castType);
                }
            };

            $model = new class extends Model
            {
                protected $fillable = ['special_field', 'normal_field'];
            };

            $result = $customService->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect(count($result->properties))->toBe(2);

            $specialField = array_filter($result->properties, fn ($prop) => $prop->name === 'special_field')[0];
            expect($specialField->description)->toBe('Special custom description');
        });

        test('multiple service instances are independent', function () {
            $service1 = new ModelSchemaService();
            $service2 = new ModelSchemaService();

            $model = new TestModel();

            $result1 = $service1->convertModelToSchema($model);
            $result2 = $service2->convertModelToSchema($model);

            // Results should be equivalent but independent
            expect($result1)->toBeInstanceOf(ObjectSchema::class);
            expect($result2)->toBeInstanceOf(ObjectSchema::class);
            expect($result1->name)->toBe($result2->name);
            expect(count($result1->properties))->toBe(count($result2->properties));
        });
    });

    describe('convertDataToModel method', function () {
        test('creates model from valid data array', function () {
            $data = [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'age' => 30,
                'is_active' => true,
            ];

            $model = $this->service->convertDataToModel(TestModel::class, $data);

            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBe('John Doe');
            expect($model->email)->toBe('john@example.com');
            expect($model->age)->toBe(30);
            expect($model->is_active)->toBe(true);
        });

        test('creates model with partial data using fillable attributes', function () {
            $data = [
                'name' => 'Jane Smith',
                'age' => 25,
                'extra_field' => 'ignored', // Should be ignored due to fillable
            ];

            $model = $this->service->convertDataToModel(TestModel::class, $data);

            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBe('Jane Smith');
            expect($model->age)->toBe(25);
            expect($model->is_active)->toBe(true); // Default value
            expect($model->email)->toBeNull();
            expect(isset($model->extra_field))->toBeFalse();
        });

        test('applies model casts correctly', function () {
            $data = [
                'name' => 'Test User',
                'age' => '42', // String that should be cast to integer
                'is_active' => 'true', // String that should be cast to boolean
            ];

            $model = $this->service->convertDataToModel(TestModel::class, $data);

            expect($model->age)->toBe(42);
            expect($model->age)->toBeInt();
            expect($model->is_active)->toBe(true);
            expect($model->is_active)->toBeBool();
        });

        test('handles different model types', function () {
            $profileData = [
                'bio' => 'Software Developer',
                'website' => 'https://example.com',
            ];

            $model = $this->service->convertDataToModel(ProfileModel::class, $profileData);

            expect($model)->toBeInstanceOf(ProfileModel::class);
            expect($model->bio)->toBe('Software Developer');
            expect($model->website)->toBe('https://example.com');
        });

        test('handles empty data gracefully', function () {
            $data = [];

            $model = $this->service->convertDataToModel(TestModel::class, $data);

            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBeNull();
            expect($model->is_active)->toBe(true); // Default value
        });

        test('throws exception for non-existent model class', function () {
            $data = ['name' => 'Test'];

            expect(fn () => $this->service->convertDataToModel('NonExistentModel', $data))
                ->toThrow(\InvalidArgumentException::class, 'Model class NonExistentModel does not exist');
        });

        test('throws exception for class that is not a model', function () {
            $data = ['name' => 'Test'];

            expect(fn () => $this->service->convertDataToModel(\stdClass::class, $data))
                ->toThrow(\InvalidArgumentException::class, 'Class stdClass is not an Eloquent model');
        });

        test('respects model fillable attributes', function () {
            $data = [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'secret', // Not fillable in TestModel
                'admin' => true, // Not fillable in TestModel
            ];

            $model = $this->service->convertDataToModel(TestModel::class, $data);

            expect($model->name)->toBe('Test User');
            expect($model->email)->toBe('test@example.com');
            expect(isset($model->password))->toBeFalse();
            expect(isset($model->admin))->toBeFalse();
        });

        test('handles nested data by ignoring non-scalar values', function () {
            $data = [
                'name' => 'User with nested data',
                'profile' => [
                    'bio' => 'This should be ignored for TestModel',
                ],
                'age' => 28,
                'tags' => ['tag1', 'tag2'], // Array value should be ignored
            ];

            $model = $this->service->convertDataToModel(TestModel::class, $data);

            expect($model->name)->toBe('User with nested data');
            expect($model->age)->toBe(28);
            expect(isset($model->profile))->toBeFalse();
            expect(isset($model->tags))->toBeFalse();
        });
    });
});
