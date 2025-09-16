<?php

declare(strict_types=1);

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\Services\ConfigurationService;
use Illuminate\Cache\CacheManager;

describe('BaseTransformer Temperature Configuration', function () {
    beforeEach(function () {
        // Create a concrete implementation for testing
        $this->basicTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
        {
            public function prompt(): string
            {
                return 'Transform this content';
            }
        };
    });

    describe('temperature() method default behavior', function () {
        test('returns null by default', function () {
            // Use reflection to test the protected method
            $reflection = new ReflectionClass($this->basicTransformer);
            $temperatureMethod = $reflection->getMethod('temperature');
            $temperatureMethod->setAccessible(true);

            $result = $temperatureMethod->invoke($this->basicTransformer);
            expect($result)->toBeNull();
        });

        test('method signature matches specification', function () {
            $reflection = new ReflectionClass($this->basicTransformer);
            $temperatureMethod = $reflection->getMethod('temperature');

            expect($temperatureMethod->isProtected())->toBeTrue();

            // Check if the return type allows null (nullable float)
            $returnType = $temperatureMethod->getReturnType();
            expect($returnType)->not->toBeNull();
            expect($returnType->allowsNull())->toBeTrue();
            expect($returnType->getName())->toBe('float');
        });
    });

    describe('custom transformer temperature() method overrides', function () {
        test('can override temperature method with custom values', function () {
            $customTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform with custom temperature';
                }

                protected function temperature(): ?float
                {
                    return 0.7;
                }
            };

            // Test the custom temperature method using reflection
            $reflection = new ReflectionClass($customTransformer);
            $temperatureMethod = $reflection->getMethod('temperature');
            $temperatureMethod->setAccessible(true);

            $temperature = $temperatureMethod->invoke($customTransformer);

            expect($temperature)->toBe(0.7);
            expect($temperature)->toBeFloat();
        });

        test('can define different temperature values for different use cases', function () {
            // Focused transformer (low temperature)
            $focusedTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Focused transformation';
                }

                protected function temperature(): ?float
                {
                    return 0.1; // Very focused, deterministic
                }
            };

            // Balanced transformer (moderate temperature)
            $balancedTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Balanced transformation';
                }

                protected function temperature(): ?float
                {
                    return 0.7; // Balanced creativity and focus
                }
            };

            // Creative transformer (high temperature)
            $creativeTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Creative transformation';
                }

                protected function temperature(): ?float
                {
                    return 1.5; // More creative and varied outputs
                }
            };

            $focusedReflection = new ReflectionClass($focusedTransformer);
            $focusedMethod = $focusedReflection->getMethod('temperature');
            $focusedMethod->setAccessible(true);

            $balancedReflection = new ReflectionClass($balancedTransformer);
            $balancedMethod = $balancedReflection->getMethod('temperature');
            $balancedMethod->setAccessible(true);

            $creativeReflection = new ReflectionClass($creativeTransformer);
            $creativeMethod = $creativeReflection->getMethod('temperature');
            $creativeMethod->setAccessible(true);

            expect($focusedMethod->invoke($focusedTransformer))->toBe(0.1);
            expect($balancedMethod->invoke($balancedTransformer))->toBe(0.7);
            expect($creativeMethod->invoke($creativeTransformer))->toBe(1.5);
        });

        test('can return null to use provider defaults', function () {
            $defaultTemperatureTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Use provider default temperature';
                }

                protected function temperature(): ?float
                {
                    return null; // Explicitly use provider default
                }
            };

            $reflection = new ReflectionClass($defaultTemperatureTransformer);
            $temperatureMethod = $reflection->getMethod('temperature');
            $temperatureMethod->setAccessible(true);

            $temperature = $temperatureMethod->invoke($defaultTemperatureTransformer);

            expect($temperature)->toBeNull();
        });
    });

    describe('temperature range validation scenarios', function () {
        test('supports valid temperature range values', function () {
            $validTemperatures = [
                0.0,    // Minimum valid value (completely deterministic)
                0.1,    // Very focused
                0.3,    // Focused
                0.5,    // Slightly focused
                0.7,    // Balanced (common default)
                1.0,    // Slightly creative
                1.2,    // Creative
                1.5,    // Very creative
                2.0,    // Maximum typical value
            ];

            foreach ($validTemperatures as $temp) {
                $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $temp) extends BaseTransformer
                {
                    private float $testTemperature;

                    public function __construct(CacheManager $cache, ConfigurationService $configuration, float $temperature)
                    {
                        parent::__construct($cache, $configuration);
                        $this->testTemperature = $temperature;
                    }

                    public function prompt(): string
                    {
                        return 'Temperature range test';
                    }

                    protected function temperature(): ?float
                    {
                        return $this->testTemperature;
                    }
                };

                $reflection = new ReflectionClass($transformer);
                $temperatureMethod = $reflection->getMethod('temperature');
                $temperatureMethod->setAccessible(true);

                $result = $temperatureMethod->invoke($transformer);

                expect($result)->toBe($temp);
                expect($result)->toBeFloat();
                expect($result)->toBeGreaterThanOrEqual(0.0);
                expect($result)->toBeLessThanOrEqual(2.0);
            }
        });

        test('handles edge case temperature values', function () {
            // Test boundary values and common edge cases
            $edgeCaseTemperatures = [
                0.0,     // Absolute minimum (deterministic)
                0.001,   // Very close to zero
                0.999,   // Close to 1.0
                1.0,     // Exactly 1.0
                1.999,   // Close to 2.0
                2.0,     // Common maximum
            ];

            foreach ($edgeCaseTemperatures as $temp) {
                $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $temp) extends BaseTransformer
                {
                    private float $testTemperature;

                    public function __construct(CacheManager $cache, ConfigurationService $configuration, float $temperature)
                    {
                        parent::__construct($cache, $configuration);
                        $this->testTemperature = $temperature;
                    }

                    public function prompt(): string
                    {
                        return 'Edge case temperature test';
                    }

                    protected function temperature(): ?float
                    {
                        return $this->testTemperature;
                    }
                };

                $reflection = new ReflectionClass($transformer);
                $temperatureMethod = $reflection->getMethod('temperature');
                $temperatureMethod->setAccessible(true);

                $result = $temperatureMethod->invoke($transformer);

                expect($result)->toBe($temp);
                expect($result)->toBeFloat();
            }
        });

        test('handles negative temperature values gracefully', function () {
            // Note: This test documents that the BaseTransformer itself doesn't validate
            // temperature ranges - that's the responsibility of the provider/Prism
            $negativeTemperatureTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Negative temperature test';
                }

                protected function temperature(): ?float
                {
                    return -0.5; // Invalid, but BaseTransformer should not validate
                }
            };

            $reflection = new ReflectionClass($negativeTemperatureTransformer);
            $temperatureMethod = $reflection->getMethod('temperature');
            $temperatureMethod->setAccessible(true);

            // Should return the value as-is without validation
            $result = $temperatureMethod->invoke($negativeTemperatureTransformer);

            expect($result)->toBe(-0.5);
            expect($result)->toBeFloat();
        });

        test('handles extremely high temperature values gracefully', function () {
            // Note: This test documents that the BaseTransformer itself doesn't validate
            // temperature ranges - that's the responsibility of the provider/Prism
            $highTemperatureTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'High temperature test';
                }

                protected function temperature(): ?float
                {
                    return 5.0; // Very high, but BaseTransformer should not validate
                }
            };

            $reflection = new ReflectionClass($highTemperatureTransformer);
            $temperatureMethod = $reflection->getMethod('temperature');
            $temperatureMethod->setAccessible(true);

            // Should return the value as-is without validation
            $result = $temperatureMethod->invoke($highTemperatureTransformer);

            expect($result)->toBe(5.0);
            expect($result)->toBeFloat();
        });
    });

    describe('temperature method inheritance', function () {
        test('child classes can override parent temperature', function () {
            // Create a base transformer with temperature
            $baseTransformerClass = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Base transformer';
                }

                protected function temperature(): ?float
                {
                    return 0.5; // Base temperature
                }
            };

            // Create a child transformer that overrides temperature
            $childTransformerClass = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Child transformer';
                }

                protected function temperature(): ?float
                {
                    return 1.2; // Child-specific temperature
                }
            };

            $baseReflection = new ReflectionClass($baseTransformerClass);
            $baseTemperatureMethod = $baseReflection->getMethod('temperature');
            $baseTemperatureMethod->setAccessible(true);

            $childReflection = new ReflectionClass($childTransformerClass);
            $childTemperatureMethod = $childReflection->getMethod('temperature');
            $childTemperatureMethod->setAccessible(true);

            $baseTemperature = $baseTemperatureMethod->invoke($baseTransformerClass);
            $childTemperature = $childTemperatureMethod->invoke($childTransformerClass);

            expect($baseTemperature)->toBe(0.5);
            expect($childTemperature)->toBe(1.2);
            expect($baseTemperature)->not->toBe($childTemperature);
        });

        test('child classes can call parent temperature with modifications', function () {
            // Create a base transformer with temperature
            $baseTransformerClass = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Base transformer';
                }

                protected function temperature(): ?float
                {
                    return 0.7; // Base temperature
                }
            };

            // Create a child transformer that modifies parent temperature
            $childTransformerClass = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Child transformer';
                }

                protected function temperature(): ?float
                {
                    $baseTemperature = 0.7; // Simulating parent call

                    return $baseTemperature + 0.3; // Increase creativity
                }
            };

            $childReflection = new ReflectionClass($childTransformerClass);
            $childTemperatureMethod = $childReflection->getMethod('temperature');
            $childTemperatureMethod->setAccessible(true);

            $childTemperature = $childTemperatureMethod->invoke($childTransformerClass);

            expect($childTemperature)->toBe(1.0);
            expect($childTemperature)->toBeFloat();
        });
    });
});
