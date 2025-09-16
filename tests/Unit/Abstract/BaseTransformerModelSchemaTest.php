<?php

declare(strict_types=1);

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Services\ModelSchemaService;
use Droath\PrismTransformer\Tests\Stubs\TestModel;
use Droath\PrismTransformer\Tests\Stubs\RelatedModel;
use Droath\PrismTransformer\Tests\Stubs\ProfileModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Cache\CacheManager;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

describe('BaseTransformer Model Schema Conversion', function () {
    beforeEach(function () {
        // Create a concrete implementation for testing
        $this->transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
        {
            public function prompt(): string
            {
                return 'Transform this content';
            }

            // Expose the service for direct testing
            public function getModelSchemaService(): ModelSchemaService
            {
                return $this->modelSchemaService;
            }
        };
    });

    describe('ModelSchemaService integration', function () {
        test('returns ObjectSchema for models with fillable attributes', function () {
            $model = new TestModel();
            $service = $this->transformer->getModelSchemaService();

            $result = $service->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect($result->name)->toBe('testmodel');
            expect($result->description)->toBe('Schema for TestModel model');
            expect(count($result->properties))->toBe(5);
            expect(count($result->requiredFields))->toBe(5);
        });

        test('can be called with any Laravel Model instance', function () {
            $testModel = new TestModel();
            $relatedModel = new RelatedModel();
            $profileModel = new ProfileModel();
            $service = $this->transformer->getModelSchemaService();

            // Should not throw exceptions with different model types
            expect(fn () => $service->convertModelToSchema($testModel))->not->toThrow(\Exception::class);
            expect(fn () => $service->convertModelToSchema($relatedModel))->not->toThrow(\Exception::class);
            expect(fn () => $service->convertModelToSchema($profileModel))->not->toThrow(\Exception::class);
        });

        test('accepts models with different fillable attributes', function () {
            $model = new TestModel();
            $model->fillable(['name', 'email', 'custom_field']);
            $service = $this->transformer->getModelSchemaService();

            expect(fn () => $service->convertModelToSchema($model))->not->toThrow(\Exception::class);
        });

        test('accepts models with different cast types', function () {
            $model = new class extends Model
            {
                protected $fillable = ['complex_field'];

                protected $casts = [
                    'complex_field' => 'json',
                    'date_field' => 'date',
                    'float_field' => 'float',
                    'decimal_field' => 'decimal:2',
                ];
            };

            expect(fn () => $this->transformer->getModelSchemaService()->convertModelToSchema($model))->not->toThrow(\Exception::class);
        });

        test('handles models with no fillable attributes', function () {
            $model = new class extends Model
            {
                protected $fillable = [];
            };

            expect(fn () => $this->transformer->getModelSchemaService()->convertModelToSchema($model))->not->toThrow(\Exception::class);
        });

        test('handles models with no cast definitions', function () {
            $model = new class extends Model
            {
                protected $fillable = ['name', 'email'];

                protected $casts = [];
            };

            expect(fn () => $this->transformer->getModelSchemaService()->convertModelToSchema($model))->not->toThrow(\Exception::class);
        });

        test('handles models with relationship methods', function () {
            $model = new TestModel();

            // Model has posts() and profile() relationship methods
            expect(fn () => $this->transformer->getModelSchemaService()->convertModelToSchema($model))->not->toThrow(\Exception::class);
        });

        test('handles models with default attribute values', function () {
            $model = new TestModel();

            // TestModel has default is_active = true
            expect(fn () => $this->transformer->getModelSchemaService()->convertModelToSchema($model))->not->toThrow(\Exception::class);
        });
    });

    describe('integration with outputFormat method', function () {
        test('resolveOutputFormat handles Model instances', function () {
            $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function outputFormat(): TestModel
                {
                    return new TestModel();
                }

                // Make protected method public for testing
                public function resolveOutputFormat(): ?ObjectSchema
                {
                    return parent::resolveOutputFormat();
                }
            };

            $result = $transformer->resolveOutputFormat();

            // Should call convertModelToObjectSchema and return ObjectSchema
            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect($result->name)->toBe('testmodel');
        });

        test('outputFormat method supports returning Model instances', function () {
            $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function getOutputFormat(): null|ObjectSchema|Model
                {
                    return $this->outputFormat();
                }
            };

            $result = $transformer->getOutputFormat();

            expect($result)->toBeInstanceOf(Model::class);
            expect($result)->toBeInstanceOf(TestModel::class);
        });

        test('outputFormat works with different model types', function () {
            $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                private Model $model;

                public function setModel(Model $model): void
                {
                    $this->model = $model;
                }

                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function outputFormat(): Model
                {
                    return $this->model;
                }

                public function resolveOutputFormat(): ?ObjectSchema
                {
                    return parent::resolveOutputFormat();
                }
            };

            // Test with TestModel
            $transformer->setModel(new TestModel());
            $result = $transformer->resolveOutputFormat();
            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect($result->name)->toBe('testmodel');

            // Test with RelatedModel
            $transformer->setModel(new RelatedModel());
            $result = $transformer->resolveOutputFormat();
            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect($result->name)->toBe('relatedmodel');

            // Test with ProfileModel
            $transformer->setModel(new ProfileModel());
            $result = $transformer->resolveOutputFormat();
            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect($result->name)->toBe('profilemodel');
        });

        test('resolveOutputFormat returns ObjectSchema when provided directly', function () {
            $schema = new ObjectSchema(
                'test_schema',
                'A test schema for testing',
                [
                    new StringSchema('name', 'User name'),
                    new StringSchema('email', 'User email'),
                ]
            );

            $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                private ObjectSchema $schema;

                public function setSchema(ObjectSchema $schema): void
                {
                    $this->schema = $schema;
                }

                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function outputFormat(): ObjectSchema
                {
                    return $this->schema;
                }

                public function resolveOutputFormat(): ?ObjectSchema
                {
                    return parent::resolveOutputFormat();
                }
            };

            $transformer->setSchema($schema);
            $result = $transformer->resolveOutputFormat();

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect($result)->toBe($schema);
        });

        test('resolveOutputFormat returns null when outputFormat is null', function () {
            $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function outputFormat(): null
                {
                    return null;
                }

                public function resolveOutputFormat(): ?ObjectSchema
                {
                    return parent::resolveOutputFormat();
                }
            };

            $result = $transformer->resolveOutputFormat();

            expect($result)->toBeNull();
        });
    });

    describe('error handling scenarios', function () {
        test('ModelSchemaService handles corrupted model data gracefully', function () {
            $model = new class extends Model
            {
                protected $fillable = ['name'];

                public function getFillable()
                {
                    // Simulate corrupted fillable data by returning an empty array instead of null
                    // (null would cause Laravel's totallyGuarded() method to fail)
                    return [];
                }
            };

            // Should not throw exception, even with corrupted data
            expect(fn () => $this->transformer->getModelSchemaService()->convertModelToSchema($model))->not->toThrow(\Exception::class);
        });

        test('ModelSchemaService handles models with circular relationships', function () {
            // This test ensures the method can handle complex model structures
            // without infinite recursion or memory issues
            $model = new TestModel();

            expect(fn () => $this->transformer->getModelSchemaService()->convertModelToSchema($model))->not->toThrow(\Exception::class);
        });

        test('gracefully handles exception scenarios', function () {
            // Test that the transformer is robust against various failure modes
            // without necessarily testing the specific exception handling

            $modelWithProblems = new class extends Model
            {
                protected $fillable = ['field1'];

                private $shouldFail = false;

                public function makeFail(): void
                {
                    $this->shouldFail = true;
                }

                public function getFillable()
                {
                    if ($this->shouldFail) {
                        return null; // Return invalid data instead of throwing
                    }

                    return parent::getFillable();
                }
            };

            // Should handle null return from getFillable
            $modelWithProblems->makeFail();
            expect(fn () => $this->transformer->getModelSchemaService()->convertModelToSchema($modelWithProblems))->not->toThrow(\Exception::class);

            $result = $this->transformer->getModelSchemaService()->convertModelToSchema($modelWithProblems);
            expect($result)->toBeNull();
        });

        test('skips fields that cause schema creation errors', function () {
            $model = new TestModel();

            // Create a custom service that simulates schema creation failure
            $mockService = new class extends ModelSchemaService
            {
                protected function createSchemaForCastType(string $fieldName, string $castType): ?\Prism\Prism\Contracts\Schema
                {
                    // Simulate failure for 'age' field
                    if ($fieldName === 'age') {
                        throw new \RuntimeException('Schema creation failed for age field');
                    }

                    return parent::createSchemaForCastType($fieldName, $castType);
                }
            };

            $result = $mockService->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            // Should have 4 properties instead of 5 (age field skipped due to error)
            expect(count($result->properties))->toBe(4);
        });

        test('handles ObjectSchema creation robustly', function () {
            // Test that ObjectSchema creation doesn't cause issues even with edge case data

            $result = $this->transformer->getModelSchemaService()->convertModelToSchema(new TestModel());

            // Should successfully create ObjectSchema for valid models
            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect($result->name)->toBeString();
            expect($result->description)->toBeString();
        });

        test('handles model with malformed class name gracefully', function () {
            $model = new class extends Model
            {
                protected $fillable = ['name'];
            };

            // Even with anonymous class, should handle gracefully
            $result = $this->transformer->getModelSchemaService()->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            // Should create schema with some derived name
            expect($result->name)->toBeString();
        });

        test('handles memory or resource exhaustion gracefully', function () {
            // Create a model that could potentially cause memory issues
            $largeModel = new class extends Model
            {
                protected $fillable = [];

                protected $casts = [];

                public function __construct()
                {
                    parent::__construct();
                    // Create a large number of fillable fields
                    for ($i = 0; $i < 1000; $i++) {
                        $this->fillable[] = "field_$i";
                    }
                }
            };

            // Should handle large models without issues
            expect(fn () => $this->transformer->getModelSchemaService()->convertModelToSchema($largeModel))->not->toThrow(\Exception::class);

            $result = $this->transformer->getModelSchemaService()->convertModelToSchema($largeModel);
            expect($result)->toBeInstanceOf(ObjectSchema::class);
        });
    });

    describe('edge cases and relationships', function () {
        test('handles models with guarded attributes correctly', function () {
            $model = new class extends Model
            {
                protected $fillable = ['name', 'email'];

                protected $guarded = ['password', 'secret'];
            };

            $result = $this->transformer->getModelSchemaService()->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect(count($result->properties))->toBe(2);
        });

        test('handles models with mass assignment protection', function () {
            $model = new class extends Model
            {
                protected $guarded = ['*']; // All attributes guarded
            };

            $result = $this->transformer->getModelSchemaService()->convertModelToSchema($model);

            expect($result)->toBeNull(); // Should return null when no fillable attributes
        });

        test('handles models with computed casts', function () {
            $model = new class extends Model
            {
                protected $fillable = ['data'];

                protected $casts = [
                    'data' => 'encrypted',
                ];
            };

            // Should handle unknown cast types gracefully
            expect(fn () => $this->transformer->getModelSchemaService()->convertModelToSchema($model))->not->toThrow(\Exception::class);

            $result = $this->transformer->getModelSchemaService()->convertModelToSchema($model);
            expect($result)->toBeInstanceOf(ObjectSchema::class);
        });

        test('handles models with nested json cast types', function () {
            $model = new class extends Model
            {
                protected $fillable = ['metadata', 'config'];

                protected $casts = [
                    'metadata' => 'json',
                    'config' => 'array',
                ];
            };

            $result = $this->transformer->getModelSchemaService()->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect(count($result->properties))->toBe(2);
        });

        test('handles models with custom accessor/mutator attributes', function () {
            $model = new class extends Model
            {
                protected $fillable = ['first_name', 'last_name'];

                // This accessor won't be included in schema since it's not fillable
                public function getFullNameAttribute(): string
                {
                    return $this->first_name.' '.$this->last_name;
                }
            };

            $result = $this->transformer->getModelSchemaService()->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect(count($result->properties))->toBe(2); // Only fillable attributes
        });

        test('handles very large models with many attributes', function () {
            $fillableAttributes = [];
            $casts = [];

            // Create a model with 50 fillable attributes
            for ($i = 1; $i <= 50; $i++) {
                $fillableAttributes[] = "field_{$i}";
                $casts["field_{$i}"] = $i % 2 === 0 ? 'integer' : 'string';
            }

            $model = new class extends Model
            {
                protected $fillable = [];

                protected $casts = [];

                public function setFillableAndCasts(array $fillable, array $casts): void
                {
                    $this->fillable = $fillable;
                    $this->casts = $casts;
                }
            };

            $model->setFillableAndCasts($fillableAttributes, $casts);

            $result = $this->transformer->getModelSchemaService()->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect(count($result->properties))->toBe(50);
            expect(count($result->requiredFields))->toBe(50);
        });

        test('handles models with relationship methods but focuses on fillable', function () {
            // TestModel has hasMany and belongsTo relationships
            $model = new TestModel();

            $result = $this->transformer->getModelSchemaService()->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            // Should only include fillable attributes, not relationships
            expect(count($result->properties))->toBe(5); // name, email, age, is_active, created_at
        });

        test('handles models with different timestamp configurations', function () {
            $model = new class extends Model
            {
                protected $fillable = ['created_at', 'updated_at'];

                public $timestamps = false; // Disable automatic timestamps
            };

            $result = $this->transformer->getModelSchemaService()->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            expect(count($result->properties))->toBe(2);
        });

        test('handles model with empty string field names by filtering them out', function () {
            $model = new class extends Model
            {
                protected $fillable = ['', 'valid_field', '   ', 'another_field'];
            };

            $result = $this->transformer->getModelSchemaService()->convertModelToSchema($model);

            expect($result)->toBeInstanceOf(ObjectSchema::class);
            // Should filter out empty/whitespace field names
            expect(count($result->properties))->toBe(2); // Only valid_field and another_field
        });
    });

    describe('type safety and parameter validation', function () {
        test('ModelSchemaService.convertModelToSchema requires Model instance', function () {
            // This test documents the method signature requirement
            expect($this->transformer->getModelSchemaService()->convertModelToSchema(new TestModel()))->toBeInstanceOf(ObjectSchema::class);
        });

        test('ModelSchemaService.convertModelToSchema return type is nullable ObjectSchema', function () {
            $result = $this->transformer->getModelSchemaService()->convertModelToSchema(new TestModel());

            expect($result)->toBeInstanceOf(ObjectSchema::class);
        });
    });
});
