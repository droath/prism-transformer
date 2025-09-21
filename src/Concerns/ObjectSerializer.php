<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Concerns;

/**
 * Trait for secure object serialization and deserialization functionality.
 *
 * This trait provides secure serialization capabilities with class whitelisting
 * to prevent arbitrary code execution during deserialization. It implements
 * the Principle of Least Privilege by only allowing trusted classes to be
 * resolved during the deserialization process.
 *
 * Security Features:
 * - Class whitelisting prevents instantiation of arbitrary classes
 * - Type validation ensures properties can only receive compatible values
 * - Proper error handling with descriptive exceptions
 * - Safe defaults (empty whitelist = no class resolution)
 *
 * @example Basic usage:
 * ```php
 * class MyClass
 * {
 *     use ObjectSerializer;
 *
 *     protected function whitelistSerializationClasses(): array
 *     {
 *         return [MyTrustedService::class];
 *     }
 * }
 * ```
 */
trait ObjectSerializer
{
    /**
     * Serialize object state for secure storage.
     *
     * This method safely serializes all object properties, converting objects
     * to their class names to avoid serializing complex object graphs. The
     * serialized data can be safely stored and later restored using __unserialize().
     *
     * @return array<string, mixed> Serializable object state
     */
    public function __serialize(): array
    {
        $properties = [];
        $reflection = new \ReflectionClass($this);

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($this);

            $properties[$property->getName()] = match (true) {
                is_scalar($value) || is_null($value) || is_array($value) => $value,
                is_object($value) => get_class($value),
                default => null,
            };
        }

        return $properties;
    }

    /**
     * Restore the object state from serialized data with security validation.
     *
     * This method safely restores object properties from serialized data,
     * applying security checks to prevent arbitrary class instantiation.
     * Only classes in the whitelist can be resolved during deserialization.
     *
     * @param array<string, mixed> $data Serialized object state
     *
     * @throws \InvalidArgumentException When class resolution fails or is not allowed
     */
    public function __unserialize(array $data): void
    {
        $reflection = new \ReflectionClass($this);
        $whitelistedClasses = $this->whitelistSerializationClasses();

        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();

            if (! array_key_exists($propertyName, $data)) {
                continue;
            }

            $value = $data[$propertyName];

            if (is_string($value) && str_contains($value, '\\')) {
                if (empty($whitelistedClasses)) {
                    continue;
                }
                $value = $this->resolveSerializedClass(
                    $value,
                    $whitelistedClasses
                );
            }

            if ($this->canAssignValueToProperty($property, $value)) {
                $property->setAccessible(true);
                $property->setValue($this, $value);
            }
        }
    }

    /**
     * Define the whitelist of classes allowed for secure deserialization.
     *
     * Override this method to specify which classes are safe to instantiate
     * during deserialization. An empty array (default) means no classes will
     * be resolved, which is the safest option.
     *
     * @return array<string> Array of fully qualified class names that are safe to deserialize
     *
     * @example Implementation:
     * ```php
     * protected function whitelistSerializationClasses(): array
     * {
     *     return [
     *         MyTrustedService::class,
     *         AnotherSafeClass::class,
     *     ];
     * }
     * ```
     */
    protected function whitelistSerializationClasses(): array
    {
        return [];
    }

    /**
     * Safely resolve a serialized class name with security validation.
     *
     * This method performs security checks before attempting to resolve a class
     * name to an instance. It ensures only whitelisted classes can be instantiated
     * and provides detailed error messages for debugging.
     *
     * @param string $className The class name to resolve
     * @param array<string> $whitelistedClasses Allowed class names for deserialization
     *
     * @return object|string The resolved instance or original string if class doesn't exist
     *
     * @throws \InvalidArgumentException When a class is not allowlisted or resolution fails
     */
    protected function resolveSerializedClass(
        string $className,
        array $whitelistedClasses
    ): object|string {
        if (! in_array($className, $whitelistedClasses, true)) {
            throw new \InvalidArgumentException(
                "Class '{$className}' is not whitelisted for secure deserialization. ".
                'Add it to whitelistSerializationClasses() if this class is trusted.'
            );
        }

        if (! class_exists($className)) {
            return $className;
        }

        try {
            return resolve($className);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(
                "Failed to resolve whitelisted class '{$className}': ".$e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Check if a value can be assigned to a property based on type hints.
     *
     * This method performs type compatibility checks to ensure that values
     * being deserialized are compatible with the target property's type hints.
     * This prevents type safety violations during deserialization.
     *
     * @param \ReflectionProperty $property The target property
     * @param mixed $value The value to assign
     *
     * @return bool True if the value can be safely assigned to the property
     */
    protected function canAssignValueToProperty(
        \ReflectionProperty $property,
        mixed $value
    ): bool {
        $type = $property->getType();

        if (! $type instanceof \ReflectionNamedType) {
            return true;
        }
        $typeName = $type->getName();

        // Allow null if the type is nullable
        if ($value === null && $type->allowsNull()) {
            return true;
        }

        return match ($typeName) {
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
            default => ($value instanceof $typeName),
        };
    }
}
