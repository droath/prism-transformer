<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Services;

use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Contracts\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Service responsible for converting Laravel Eloquent models to Prism
 * ObjectSchema instances.
 *
 * This service provides clean separation of concerns by extracting model
 * schema
 * conversion logic from transformers. It handles the complexities of Laravel's
 * cast system and provides robust error handling for edge cases.
 *
 * @example Basic usage:
 * ```php
 * $service = new ModelSchemaService();
 * $schema = $service->convertModelToSchema($userModel);
 *
 * if ($schema !== null) {
 *     // Use the schema for structured transformations
 *     $prism->withSchema($schema);
 * }
 * ```
 *
 * @api
 */
class ModelSchemaService
{
    /**
     * Convert a Laravel Eloquent model to a Prism ObjectSchema.
     *
     * This method extracts fillable attributes and their cast types from the
     * model to generate a corresponding ObjectSchema for structured LLM
     * responses. Returns null if the model cannot be converted or has no
     * fillable attributes.
     *
     * @param Model $model The Eloquent model to convert
     * @param array<string, array{required?: bool, type?: string}> $config
     *     Optional configuration mapping field names to their settings
     *
     * @return ObjectSchema|null The generated schema or null if conversion fails
     */
    public function convertModelToSchema(Model $model, array $config = []): ?ObjectSchema
    {
        $schemaData = ! empty($config)
            ? $this->buildSchemaFromConfig($model, $config)
            : $this->buildSchemaProperties(
                $this->extractModelAttributes($model) ?? []
            );

        if (empty($schemaData['properties'])) {
            return null;
        }

        return $this->createObjectSchema($model, $schemaData);
    }

    /**
     * Convert an array of data into a populated Laravel Eloquent model.
     *
     * This method takes raw data (typically from JSON) and converts it into a
     * properly hydrated Laravel model instance. It respects model-fillable
     * attributes and applies model casts automatically.
     *
     * @param string $modelClass The fully qualified model class name
     * @param array $data The data to populate the model with
     *
     * @return Model The populated model instance
     *
     * @throws \InvalidArgumentException If the model class is invalid
     *
     * @example Basic usage:
     * ```php
     * $service = new ModelSchemaService();
     * $data = ['name' => 'John', 'age' => 30];
     * $user = $service->convertDataToModel(User::class, $data);
     * ```
     */
    public function convertDataToModel(string $modelClass, array $data): Model
    {
        if (! class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class {$modelClass} does not exist");
        }

        if (! is_subclass_of($modelClass, Model::class)) {
            $className = basename(str_replace('\\', '/', $modelClass));
            throw new \InvalidArgumentException("Class {$className} is not an Eloquent model");
        }

        return $modelClass::make($data);
    }

    /**
     * Create a Prism schema for a Laravel cast type using a modern match
     * expression.
     *
     * This method maps Laravel's cast types to appropriate Prism schema
     * objects, providing type-safe schema generation for structured LLM
     * responses.
     *
     * @param string $fieldName The name of the field
     * @param string $castType The Laravel cast type
     *
     * @return Schema|null The corresponding Prism schema or null if unsupported
     */
    protected function createSchemaForCastType(string $fieldName, string $castType): ?Schema
    {
        $description = ucfirst(str_replace('_', ' ', $fieldName));

        return match (true) {
            $castType === 'boolean' || $castType === 'bool' => new BooleanSchema($fieldName, $description),

            in_array($castType, ['integer', 'int'], true) => new NumberSchema($fieldName, $description),

            in_array($castType, ['float', 'double', 'real'], true) => new NumberSchema($fieldName, $description),

            str_starts_with($castType, 'decimal') => new NumberSchema($fieldName, $description),

            in_array($castType, ['array', 'json'], true) => new ArraySchema($fieldName, $description, new StringSchema('item', 'Array item')),

            $castType === 'collection' => new ArraySchema($fieldName, $description, new StringSchema('item', 'Collection item')),

            in_array($castType, ['date', 'datetime', 'timestamp'], true) => new StringSchema($fieldName, "$description (ISO 8601 format)"),

            default => new StringSchema($fieldName, $description),
        };
    }

    /**
     * Extract fillable attributes and casts from a model.
     *
     * @param Model $model The model to extract attributes from
     *
     * @return array{fillable: array<string>, casts: array<string, string>}|null
     */
    private function extractModelAttributes(Model $model): ?array
    {
        try {
            $fillable = $model->getFillable();
            $casts = $model->getCasts();
        } catch (\Throwable) {
            return null;
        }

        if (empty($fillable)) {
            return null;
        }

        return [
            'casts' => $casts,
            'fillable' => $fillable,
        ];
    }

    /**
     * Build schema properties from an explicit configuration.
     *
     * @param Model $model The model to get fallback information from
     * @param array<string, array{required?: bool, type?: string}> $config
     *
     * @return array{properties: array<Schema>, requiredFields: array<string>}
     */
    private function buildSchemaFromConfig(Model $model, array $config): array
    {
        $properties = [];
        $requiredFields = [];

        $modelCasts = $model->getCasts();

        foreach ($config as $fieldName => $fieldConfig) {
            if (! $this->isValidFieldName($fieldName)) {
                continue;
            }
            $isRequired = $fieldConfig['required'] ?? false;
            $fieldType = $fieldConfig['type'] ?? $modelCasts[$fieldName] ?? 'string';

            if ($schema = $this->createSchemaForCastType($fieldName, $fieldType)) {
                $properties[] = $schema;
                if ($isRequired) {
                    $requiredFields[] = $fieldName;
                }
            }
        }

        return [
            'properties' => $properties,
            'requiredFields' => $requiredFields,
        ];
    }

    /**
     * Build schema properties from model attributes.
     *
     * @param array{fillable: array<string>, casts: array<string, string>} $modelAttributes
     *
     * @return array{properties: array<Schema>, requiredFields: array<string>}
     */
    private function buildSchemaProperties(array $modelAttributes): array
    {
        $properties = [];
        $requiredFields = [];

        $casts = $modelAttributes['casts'] ?? [];
        $fillable = $modelAttributes['fillable'] ?? [];

        foreach ($fillable as $field) {
            if (! $this->isValidFieldName($field)) {
                continue;
            }

            try {
                $castType = $casts[$field] ?? 'string';
                if ($schema = $this->createSchemaForCastType($field, $castType)) {
                    $properties[] = $schema;
                    $requiredFields[] = $field;
                }
            } catch (\Throwable) {
                // Skip fields that fail schema creation
                continue;
            }
        }

        return [
            'properties' => $properties,
            'requiredFields' => $requiredFields,
        ];
    }

    /**
     * Validate that a field name is valid.
     *
     * @param mixed $field The field to validate
     *
     * @return bool True if the field name is valid
     */
    private function isValidFieldName(mixed $field): bool
    {
        return is_string($field) && trim($field) !== '';
    }

    /**
     * Create an ObjectSchema instance from model and schema data.
     *
     * @param Model $model The source model
     * @param array{properties: array<Schema>, requiredFields: array<string>} $schemaData
     *
     * @return ObjectSchema|null The created schema or null if creation fails
     */
    private function createObjectSchema(Model $model, array $schemaData): ?ObjectSchema
    {
        try {
            $modelClass = class_basename($model);

            return new ObjectSchema(
                strtolower($modelClass),
                "Schema for {$modelClass} model",
                $schemaData['properties'],
                $schemaData['requiredFields']
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
