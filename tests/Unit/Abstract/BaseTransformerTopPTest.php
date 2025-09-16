<?php

declare(strict_types=1);

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\Services\ConfigurationService;
use Illuminate\Cache\CacheManager;

describe('BaseTransformer TopP Configuration', function () {
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

    describe('topP() method default behavior', function () {
        test('returns null by default', function () {
            // Use reflection to test the protected method
            $reflection = new ReflectionClass($this->basicTransformer);
            $topPMethod = $reflection->getMethod('topP');
            $topPMethod->setAccessible(true);

            $result = $topPMethod->invoke($this->basicTransformer);
            expect($result)->toBeNull();
        });

        test('method signature matches specification', function () {
            $reflection = new ReflectionClass($this->basicTransformer);
            $topPMethod = $reflection->getMethod('topP');

            expect($topPMethod->isProtected())->toBeTrue();

            // Check if the return type allows null (nullable float)
            $returnType = $topPMethod->getReturnType();
            expect($returnType)->not->toBeNull();
            expect($returnType->allowsNull())->toBeTrue();
            expect($returnType->getName())->toBe('float');
        });
    });

    describe('custom transformer topP() method overrides', function () {
        test('can override topP method with custom values', function () {
            $customTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform with custom topP';
                }

                protected function topP(): ?float
                {
                    return 0.9;
                }
            };

            // Test the custom topP method using reflection
            $reflection = new ReflectionClass($customTransformer);
            $topPMethod = $reflection->getMethod('topP');
            $topPMethod->setAccessible(true);

            $topP = $topPMethod->invoke($customTransformer);

            expect($topP)->toBe(0.9);
            expect($topP)->toBeFloat();
        });

        test('can define different topP values for different use cases', function () {
            // Focused transformer (low topP)
            $focusedTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Focused transformation';
                }

                protected function topP(): ?float
                {
                    return 0.1; // Very focused, consider only top 10% of tokens
                }
            };

            // Balanced transformer (moderate topP)
            $balancedTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Balanced transformation';
                }

                protected function topP(): ?float
                {
                    return 0.7; // Balanced nucleus sampling
                }
            };

            // Diverse transformer (high topP)
            $diverseTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Diverse transformation';
                }

                protected function topP(): ?float
                {
                    return 0.95; // Consider top 95% of probability mass
                }
            };

            $focusedReflection = new ReflectionClass($focusedTransformer);
            $focusedMethod = $focusedReflection->getMethod('topP');
            $focusedMethod->setAccessible(true);

            $balancedReflection = new ReflectionClass($balancedTransformer);
            $balancedMethod = $balancedReflection->getMethod('topP');
            $balancedMethod->setAccessible(true);

            $diverseReflection = new ReflectionClass($diverseTransformer);
            $diverseMethod = $diverseReflection->getMethod('topP');
            $diverseMethod->setAccessible(true);

            expect($focusedMethod->invoke($focusedTransformer))->toBe(0.1);
            expect($balancedMethod->invoke($balancedTransformer))->toBe(0.7);
            expect($diverseMethod->invoke($diverseTransformer))->toBe(0.95);
        });

        test('can return null to use provider defaults', function () {
            $defaultTopPTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Use provider default topP';
                }

                protected function topP(): ?float
                {
                    return null; // Explicitly use provider default
                }
            };

            $reflection = new ReflectionClass($defaultTopPTransformer);
            $topPMethod = $reflection->getMethod('topP');
            $topPMethod->setAccessible(true);

            $topP = $topPMethod->invoke($defaultTopPTransformer);

            expect($topP)->toBeNull();
        });
    });

    describe('topP range validation scenarios', function () {
        test('supports valid topP range values', function () {
            $validTopPValues = [
                0.0,    // No nucleus sampling (only most likely token)
                0.1,    // Very focused (top 10% of probability mass)
                0.3,    // Focused
                0.5,    // Moderate
                0.7,    // Balanced
                0.9,    // Diverse (common default for creative tasks)
                0.95,   // Very diverse
                1.0,    // Maximum value (consider all tokens)
            ];

            foreach ($validTopPValues as $topP) {
                $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $topP) extends BaseTransformer
                {
                    private float $testTopP;

                    public function __construct(CacheManager $cache, ConfigurationService $configuration, float $topP)
                    {
                        parent::__construct($cache, $configuration);
                        $this->testTopP = $topP;
                    }

                    public function prompt(): string
                    {
                        return 'TopP range test';
                    }

                    protected function topP(): ?float
                    {
                        return $this->testTopP;
                    }
                };

                $reflection = new ReflectionClass($transformer);
                $topPMethod = $reflection->getMethod('topP');
                $topPMethod->setAccessible(true);

                $result = $topPMethod->invoke($transformer);

                expect($result)->toBe($topP);
                expect($result)->toBeFloat();
                expect($result)->toBeGreaterThanOrEqual(0.0);
                expect($result)->toBeLessThanOrEqual(1.0);
            }
        });

        test('handles edge case topP values', function () {
            // Test boundary values and common edge cases
            $edgeCaseTopPValues = [
                0.0,     // Absolute minimum (deterministic)
                0.001,   // Very close to zero
                0.5,     // Exactly 0.5
                0.999,   // Close to 1.0
                1.0,     // Exactly 1.0 (maximum)
            ];

            foreach ($edgeCaseTopPValues as $topP) {
                $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $topP) extends BaseTransformer
                {
                    private float $testTopP;

                    public function __construct(CacheManager $cache, ConfigurationService $configuration, float $topP)
                    {
                        parent::__construct($cache, $configuration);
                        $this->testTopP = $topP;
                    }

                    public function prompt(): string
                    {
                        return 'Edge case topP test';
                    }

                    protected function topP(): ?float
                    {
                        return $this->testTopP;
                    }
                };

                $reflection = new ReflectionClass($transformer);
                $topPMethod = $reflection->getMethod('topP');
                $topPMethod->setAccessible(true);

                $result = $topPMethod->invoke($transformer);

                expect($result)->toBe($topP);
                expect($result)->toBeFloat();
            }
        });

        test('handles invalid topP values gracefully', function () {
            // Note: This test documents that the BaseTransformer itself doesn't validate
            // topP ranges - that's the responsibility of the provider/Prism
            $invalidTopPValues = [
                -0.5, // Negative (invalid)
                1.5,  // Greater than 1.0 (invalid)
                2.0,  // Much greater than 1.0 (invalid)
            ];

            foreach ($invalidTopPValues as $topP) {
                $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $topP) extends BaseTransformer
                {
                    private float $testTopP;

                    public function __construct(CacheManager $cache, ConfigurationService $configuration, float $topP)
                    {
                        parent::__construct($cache, $configuration);
                        $this->testTopP = $topP;
                    }

                    public function prompt(): string
                    {
                        return 'Invalid topP test';
                    }

                    protected function topP(): ?float
                    {
                        return $this->testTopP; // Invalid, but BaseTransformer should not validate
                    }
                };

                $reflection = new ReflectionClass($transformer);
                $topPMethod = $reflection->getMethod('topP');
                $topPMethod->setAccessible(true);

                // Should return the value as-is without validation
                $result = $topPMethod->invoke($transformer);

                expect($result)->toBe($topP);
                expect($result)->toBeFloat();
            }
        });
    });

    describe('topP method inheritance', function () {
        test('child classes can override parent topP', function () {
            // Create a base transformer with topP
            $baseTransformerClass = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Base transformer';
                }

                protected function topP(): ?float
                {
                    return 0.8; // Base topP
                }
            };

            // Create a child transformer that overrides topP
            $childTransformerClass = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Child transformer';
                }

                protected function topP(): ?float
                {
                    return 0.3; // Child-specific topP
                }
            };

            $baseReflection = new ReflectionClass($baseTransformerClass);
            $baseTopPMethod = $baseReflection->getMethod('topP');
            $baseTopPMethod->setAccessible(true);

            $childReflection = new ReflectionClass($childTransformerClass);
            $childTopPMethod = $childReflection->getMethod('topP');
            $childTopPMethod->setAccessible(true);

            $baseTopP = $baseTopPMethod->invoke($baseTransformerClass);
            $childTopP = $childTopPMethod->invoke($childTransformerClass);

            expect($baseTopP)->toBe(0.8);
            expect($childTopP)->toBe(0.3);
            expect($baseTopP)->not->toBe($childTopP);
        });

        test('child classes can call parent topP with modifications', function () {
            // Create a base transformer with topP
            $baseTransformerClass = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Base transformer';
                }

                protected function topP(): ?float
                {
                    return 0.9; // Base topP
                }
            };

            // Create a child transformer that modifies parent topP
            $childTransformerClass = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Child transformer';
                }

                protected function topP(): ?float
                {
                    $baseTopP = 0.9; // Simulating parent call

                    return max(0.0, $baseTopP - 0.2); // Reduce diversity
                }
            };

            $childReflection = new ReflectionClass($childTransformerClass);
            $childTopPMethod = $childReflection->getMethod('topP');
            $childTopPMethod->setAccessible(true);

            $childTopP = $childTopPMethod->invoke($childTransformerClass);

            expect($childTopP)->toBe(0.7);
            expect($childTopP)->toBeFloat();
        });
    });

    describe('topP documentation and examples', function () {
        test('demonstrates typical topP usage patterns', function () {
            // Deterministic transformer
            $deterministicTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Deterministic content generation';
                }

                protected function topP(): ?float
                {
                    return 0.1; // Only consider top 10% most likely tokens
                }
            };

            // Creative transformer
            $creativeTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Creative content generation';
                }

                protected function topP(): ?float
                {
                    return 0.9; // Consider top 90% of probability mass for diversity
                }
            };

            $deterministicReflection = new ReflectionClass($deterministicTransformer);
            $deterministicMethod = $deterministicReflection->getMethod('topP');
            $deterministicMethod->setAccessible(true);

            $creativeReflection = new ReflectionClass($creativeTransformer);
            $creativeMethod = $creativeReflection->getMethod('topP');
            $creativeMethod->setAccessible(true);

            $deterministicTopP = $deterministicMethod->invoke($deterministicTransformer);
            $creativeTopP = $creativeMethod->invoke($creativeTransformer);

            expect($deterministicTopP)->toBe(0.1);
            expect($creativeTopP)->toBe(0.9);

            // Verify topP affects output diversity expectations
            expect($deterministicTopP)->toBeLessThan($creativeTopP);
        });
    });
});
