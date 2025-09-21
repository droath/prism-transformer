<?php

declare(strict_types=1);

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Services\ModelSchemaService;
use Illuminate\Cache\CacheManager;

class TestableTransformer extends BaseTransformer
{
    protected ?string $customProperty = null;

    public function prompt(): string
    {
        return 'Test prompt';
    }

    public function getCustomProperty(): ?string
    {
        return $this->customProperty;
    }

    public function setCustomProperty(string $value): self
    {
        $this->customProperty = $value;

        return $this;
    }

    /**
     * Override for testing to handle mock objects properly.
     */
    protected function getObjectClassName(object $object): string
    {
        $className = get_class($object);

        // Handle Mockery mock objects in tests
        if (str_starts_with($className, 'Mockery_')) {
            $interfaces = class_implements($object);
            if ($interfaces) {
                // Return the first interface that's not a Mockery interface
                foreach ($interfaces as $interface) {
                    if (! str_starts_with($interface, 'Mockery\\')) {
                        return $interface;
                    }
                }
            }

            // Fallback: try to extract original class name from mock class name
            if (preg_match('/Mockery_\d+_(.+)/', $className, $matches)) {
                return str_replace('_', '\\', $matches[1]);
            }
        }

        return $className;
    }
}

describe('BaseTransformer Serialization', function () {
    beforeEach(function () {
        $this->cacheManager = mock(CacheManager::class);
        $this->configurationService = mock(ConfigurationService::class);
        $this->modelSchemaService = mock(ModelSchemaService::class);

        $this->transformer = new TestableTransformer(
            $this->cacheManager,
            $this->configurationService,
            $this->modelSchemaService
        );
    });

    describe('__serialize() method', function () {
        it('serializes scalar properties correctly', function () {
            $this->transformer->setCustomProperty('test value');

            $serialized = $this->transformer->__serialize();

            expect($serialized)->toBeArray()
                ->and($serialized['customProperty'])->toBe('test value');
        });

        it('converts object properties to class names', function () {
            $serialized = $this->transformer->__serialize();

            // Verify that object properties are converted to strings (class names)
            expect($serialized)->toBeArray()
                ->and($serialized['cache'])->toBeString()
                ->and($serialized['configuration'])->toBeString()
                ->and($serialized['modelSchemaService'])->toBeString();

            // Verify that objects were not serialized directly (which would be arrays/null)
            expect($serialized['cache'])->not->toBeNull()
                ->and($serialized['configuration'])->not->toBeNull()
                ->and($serialized['modelSchemaService'])->not->toBeNull();
        });

        it('handles null properties correctly', function () {
            $serialized = $this->transformer->__serialize();

            expect($serialized)->toBeArray()
                ->and($serialized['customProperty'])->toBeNull();
        });

        it('includes all properties from reflection', function () {
            $serialized = $this->transformer->__serialize();

            expect($serialized)->toHaveKeys([
                'cache',
                'configuration',
                'modelSchemaService',
                'customProperty',
            ]);
        });
    });

    describe('__unserialize() method', function () {
        it('restores scalar properties correctly', function () {
            $data = [
                'customProperty' => 'restored value',
                'cache' => CacheManager::class,
                'configuration' => ConfigurationService::class,
                'modelSchemaService' => ModelSchemaService::class,
            ];

            $newTransformer = new TestableTransformer(
                mock(CacheManager::class),
                mock(ConfigurationService::class),
                mock(ModelSchemaService::class)
            );

            $newTransformer->__unserialize($data);

            expect($newTransformer->getCustomProperty())->toBe('restored value');
        });

        it('resolves allowed class names to instances', function () {
            $mockCache = mock(CacheManager::class);
            $mockConfig = mock(ConfigurationService::class);
            $mockModelSchema = mock(ModelSchemaService::class);

            // Mock the resolve() function calls
            $this->app->instance(CacheManager::class, $mockCache);
            $this->app->instance(ConfigurationService::class, $mockConfig);
            $this->app->instance(ModelSchemaService::class, $mockModelSchema);

            $data = [
                'cache' => CacheManager::class,
                'configuration' => ConfigurationService::class,
                'modelSchemaService' => ModelSchemaService::class,
                'customProperty' => null,
            ];

            $newTransformer = new TestableTransformer(
                mock(CacheManager::class),
                mock(ConfigurationService::class),
                mock(ModelSchemaService::class)
            );

            $newTransformer->__unserialize($data);

            // Verify that dependencies were resolved correctly
            expect($newTransformer)->toBeInstanceOf(TestableTransformer::class);
        });

        it('throws exception for disallowed classes', function () {
            $data = [
                'cache' => 'SomeDisallowedClass\\NotAllowed',
                'customProperty' => null,
            ];

            $newTransformer = new TestableTransformer(
                mock(CacheManager::class),
                mock(ConfigurationService::class),
                mock(ModelSchemaService::class)
            );

            expect(fn () => $newTransformer->__unserialize($data))
                ->toThrow(InvalidArgumentException::class, 'not whitelisted for secure deserialization');
        });

        it('skips missing properties in data', function () {
            $data = [
                'customProperty' => 'test value',
                // Missing other properties
            ];

            $newTransformer = new TestableTransformer(
                mock(CacheManager::class),
                mock(ConfigurationService::class),
                mock(ModelSchemaService::class)
            );

            $newTransformer->__unserialize($data);

            expect($newTransformer->getCustomProperty())->toBe('test value');
        });
    });

    describe('whitelistSerializationClasses() method', function () {
        it('returns expected whitelisted classes', function () {
            $reflection = new ReflectionClass($this->transformer);
            $method = $reflection->getMethod('whitelistSerializationClasses');
            $method->setAccessible(true);

            $whitelistedClasses = $method->invoke($this->transformer);

            expect($whitelistedClasses)->toBeArray()
                ->and($whitelistedClasses)->toHaveCount(3)
                ->and($whitelistedClasses)->toContain(CacheManager::class)
                ->and($whitelistedClasses)->toContain(ConfigurationService::class)
                ->and($whitelistedClasses)->toContain(ModelSchemaService::class);
        });
    });

    describe('resolveSerializedClass() method', function () {
        it('throws exception for disallowed classes', function () {
            $reflection = new ReflectionClass($this->transformer);
            $method = $reflection->getMethod('resolveSerializedClass');
            $method->setAccessible(true);

            expect(fn () => $method->invoke(
                $this->transformer,
                'DisallowedClass',
                [CacheManager::class]
            ))->toThrow(InvalidArgumentException::class, 'not whitelisted for secure deserialization');
        });

        it('returns class name when class does not exist', function () {
            $reflection = new ReflectionClass($this->transformer);
            $method = $reflection->getMethod('resolveSerializedClass');
            $method->setAccessible(true);

            $result = $method->invoke(
                $this->transformer,
                'NonExistentClass',
                ['NonExistentClass']
            );

            expect($result)->toBe('NonExistentClass');
        });

        it('resolves existing allowed classes', function () {
            $mockInstance = mock(CacheManager::class);
            $this->app->instance(CacheManager::class, $mockInstance);

            $reflection = new ReflectionClass($this->transformer);
            $method = $reflection->getMethod('resolveSerializedClass');
            $method->setAccessible(true);

            $result = $method->invoke(
                $this->transformer,
                CacheManager::class,
                [CacheManager::class]
            );

            expect($result)->toBe($mockInstance);
        });
    });

    describe('full serialization workflow', function () {
        it('can serialize and unserialize transformer with state', function () {
            // Set up transformer with custom state
            $this->transformer->setCustomProperty('workflow test');

            // Serialize
            $serialized = serialize($this->transformer);

            // Unserialize
            $restored = unserialize($serialized);

            expect($restored)->toBeInstanceOf(TestableTransformer::class)
                ->and($restored->getCustomProperty())->toBe('workflow test')
                ->and($restored->prompt())->toBe('Test prompt');
        });
    });
});
