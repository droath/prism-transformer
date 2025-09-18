<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Tests\Unit\Services;

use Droath\PrismTransformer\Services\ModelSchemaService;
use Droath\PrismTransformer\Tests\Stubs\TestModel;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\BooleanSchema;

describe('ModelSchemaService with configuration', function () {
    beforeEach(function () {
        $this->service = new ModelSchemaService();
        $this->model = new TestModel();
    });

    describe('developer-defined configuration', function () {
        test('uses explicit required and optional fields', function () {
            $config = [
                'name' => ['required' => true],
                'email' => ['required' => true],
                'age' => ['required' => false],
            ];

            $schema = $this->service->convertModelToSchema($this->model, $config);

            expect($schema)->toBeInstanceOf(ObjectSchema::class);
            expect($schema->requiredFields)->toBe(['name', 'email']);

            // Check that all fields are present in properties
            $properties = $schema->properties;
            $propertyNames = array_map(fn ($prop) => $prop->name(), $properties);

            expect($propertyNames)->toContain('name');
            expect($propertyNames)->toContain('email');
            expect($propertyNames)->toContain('age');
        });

        test('uses explicit type overrides', function () {
            $config = [
                'name' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'age' => [
                    'required' => false,
                    'type' => 'integer',
                ],
                'is_active' => [
                    'required' => false,
                    'type' => 'boolean',
                ],
            ];

            $schema = $this->service->convertModelToSchema($this->model, $config);

            expect($schema)->toBeInstanceOf(ObjectSchema::class);

            $properties = $schema->properties;
            $propertyMap = [];
            foreach ($properties as $property) {
                $propertyMap[$property->name()] = $property;
            }

            expect($propertyMap['name'])->toBeInstanceOf(StringSchema::class);
            expect($propertyMap['age'])->toBeInstanceOf(NumberSchema::class);
            expect($propertyMap['is_active'])->toBeInstanceOf(BooleanSchema::class);
        });

        test('falls back to model casts when type not specified', function () {
            $config = [
                'name' => ['required' => true],
                'age' => [], // No type specified, should use model cast (integer)
                'is_active' => [], // No type specified, should use model cast (boolean)
            ];

            $schema = $this->service->convertModelToSchema($this->model, $config);

            expect($schema)->toBeInstanceOf(ObjectSchema::class);

            $properties = $schema->properties;
            $propertyMap = [];
            foreach ($properties as $property) {
                $propertyMap[$property->name()] = $property;
            }

            // age has integer cast in TestModel
            expect($propertyMap['age'])->toBeInstanceOf(NumberSchema::class);
            // is_active has boolean cast in TestModel
            expect($propertyMap['is_active'])->toBeInstanceOf(BooleanSchema::class);
        });

        test('defaults to string type when no cast or override', function () {
            $config = [
                'unknown_field' => ['required' => true],
            ];

            $schema = $this->service->convertModelToSchema($this->model, $config);

            expect($schema)->toBeInstanceOf(ObjectSchema::class);

            $properties = $schema->properties;
            expect($properties)->toHaveCount(1);
            expect($properties[0])->toBeInstanceOf(StringSchema::class);
            expect($properties[0]->name())->toBe('unknown_field');
        });

        test('handles empty configuration arrays gracefully', function () {
            $config = [];

            $schema = $this->service->convertModelToSchema($this->model, $config);

            // Should fall back to fillable attributes since config is empty
            expect($schema)->toBeInstanceOf(ObjectSchema::class);
        });

        test('ignores invalid field names in configuration', function () {
            $config = [
                '' => ['required' => true],
                '  ' => ['required' => false],
                'valid_field' => ['required' => true],
                'another_valid_field' => ['required' => false],
            ];

            $schema = $this->service->convertModelToSchema($this->model, $config);

            expect($schema)->toBeInstanceOf(ObjectSchema::class);

            $properties = $schema->properties;
            $propertyNames = array_map(fn ($prop) => $prop->name(), $properties);

            expect($propertyNames)->toBe(['valid_field', 'another_valid_field']);
            expect($schema->requiredFields)->toBe(['valid_field']);
        });
    });

    describe('fallback to fillable approach', function () {
        test('uses fillable attributes when config is empty', function () {
            $config = [];

            $schema = $this->service->convertModelToSchema($this->model, $config);

            expect($schema)->toBeInstanceOf(ObjectSchema::class);

            // Should include all fillable fields from TestModel
            $properties = $schema->properties;
            $propertyNames = array_map(fn ($prop) => $prop->name(), $properties);

            $expectedFields = ['name', 'email', 'age', 'is_active', 'created_at'];
            foreach ($expectedFields as $field) {
                expect($propertyNames)->toContain($field);
            }
        });

        test('uses fillable attributes when config is not provided', function () {
            // Test default parameter behavior
            $schema = $this->service->convertModelToSchema($this->model);

            expect($schema)->toBeInstanceOf(ObjectSchema::class);

            $properties = $schema->properties;
            expect($properties)->not->toBeEmpty();
        });
    });

    describe('mixed configuration scenarios', function () {
        test('handles partial configuration with defaults', function () {
            $config = [
                'name' => ['required' => true],
                // No type specified, should default to string
            ];

            $schema = $this->service->convertModelToSchema($this->model, $config);

            expect($schema)->toBeInstanceOf(ObjectSchema::class);
            expect($schema->requiredFields)->toBe(['name']);

            $properties = $schema->properties;
            expect($properties)->toHaveCount(1);
            expect($properties[0]->name())->toBe('name');
            expect($properties[0])->toBeInstanceOf(StringSchema::class);
        });

        test('defaults required to false when not specified', function () {
            $config = [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
                // Neither has 'required' set, should default to false
            ];

            $schema = $this->service->convertModelToSchema($this->model, $config);

            expect($schema)->toBeInstanceOf(ObjectSchema::class);
            expect($schema->requiredFields)->toBe([]); // No required fields

            $properties = $schema->properties;
            expect($properties)->toHaveCount(2);
        });

        test('prioritizes explicit configuration over model introspection', function () {
            $config = [
                'custom_field' => [
                    'required' => true,
                    'type' => 'boolean',
                ],
            ];

            $schema = $this->service->convertModelToSchema($this->model, $config);

            expect($schema)->toBeInstanceOf(ObjectSchema::class);

            // Should only have the explicitly configured field, not fillable fields
            $properties = $schema->properties;
            expect($properties)->toHaveCount(1);
            expect($properties[0]->name())->toBe('custom_field');
            expect($properties[0])->toBeInstanceOf(BooleanSchema::class);
        });
    });
});
