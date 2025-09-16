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
 * Service responsible for converting Laravel Eloquent models to Prism ObjectSchema instances.
 *
 * This service provides clean separation of concerns by extracting model schema
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
     * This method extracts fillable attributes and their cast types from the model
     * to generate a corresponding ObjectSchema for structured LLM responses.
     * Returns null if the model cannot be converted or has no fillable attributes.
     *
     * @param Model $model The Eloquent model to convert
     *
     * @return ObjectSchema|null The generated schema or null if conversion fails
     */
    public function convertModelToSchema(Model $model): ?ObjectSchema
    {
        $modelAttributes = $this->extractModelAttributes($model);

        if ($modelAttributes === null) {
            return null;
        }

        $schemaData = $this->buildSchemaProperties($modelAttributes);

        if (empty($schemaData['properties'])) {
            return null;
        }

        return $this->createObjectSchema($model, $schemaData);
    }

    /**
     * Create a Prism schema for a Laravel cast type using modern match expression.
     *
     * This method maps Laravel's cast types to appropriate Prism schema objects,
     * providing type-safe schema generation for structured LLM responses.
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

        foreach ($modelAttributes['fillable'] as $field) {
            if (! $this->isValidFieldName($field)) {
                continue;
            }

            try {
                $castType = $modelAttributes['casts'][$field] ?? 'string';
                $schema = $this->createSchemaForCastType($field, $castType);

                if ($schema !== null) {
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
