<?php

declare(strict_types=1);

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Services\ModelSchemaService;
use Illuminate\Cache\CacheManager;

describe('BaseTransformer Timeout Configuration', function () {
    beforeEach(function () {
        // Create a concrete implementation for testing
        $this->basicTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
        {
            public function prompt(): string
            {
                return 'Transform this content';
            }
        };
    });

    describe('timeout() method default behavior', function () {
        test('returns null by default', function () {
            // Use reflection to test the protected method
            $reflection = new ReflectionClass($this->basicTransformer);
            $timeoutMethod = $reflection->getMethod('timeout');
            $timeoutMethod->setAccessible(true);

            $result = $timeoutMethod->invoke($this->basicTransformer);
            expect($result)->toBeNull();
        });

        test('method signature matches specification', function () {
            $reflection = new ReflectionClass($this->basicTransformer);
            $timeoutMethod = $reflection->getMethod('timeout');

            expect($timeoutMethod->isProtected())->toBeTrue();

            // Check if the return type allows null (nullable int)
            $returnType = $timeoutMethod->getReturnType();
            expect($returnType)->not->toBeNull();
            expect($returnType->allowsNull())->toBeTrue();
            expect($returnType->getName())->toBe('int');
        });
    });

    describe('connectTimeout() method default behavior', function () {
        test('returns null by default', function () {
            // Use reflection to test the protected method
            $reflection = new ReflectionClass($this->basicTransformer);
            $connectTimeoutMethod = $reflection->getMethod('connectTimeout');
            $connectTimeoutMethod->setAccessible(true);

            $result = $connectTimeoutMethod->invoke($this->basicTransformer);
            expect($result)->toBeNull();
        });

        test('method signature matches specification', function () {
            $reflection = new ReflectionClass($this->basicTransformer);
            $connectTimeoutMethod = $reflection->getMethod('connectTimeout');

            expect($connectTimeoutMethod->isProtected())->toBeTrue();

            // Check if the return type allows null (nullable int)
            $returnType = $connectTimeoutMethod->getReturnType();
            expect($returnType)->not->toBeNull();
            expect($returnType->allowsNull())->toBeTrue();
            expect($returnType->getName())->toBe('int');
        });
    });

    describe('custom transformer timeout() method overrides', function () {
        test('can override timeout method with custom values', function () {
            $customTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform with custom timeout';
                }

                protected function timeout(): ?int
                {
                    return 300; // 5 minutes
                }
            };

            // Test the custom timeout method using reflection
            $reflection = new ReflectionClass($customTransformer);
            $timeoutMethod = $reflection->getMethod('timeout');
            $timeoutMethod->setAccessible(true);

            $timeout = $timeoutMethod->invoke($customTransformer);

            expect($timeout)->toBe(300);
            expect($timeout)->toBeInt();
        });

        test('can define different timeout values for different use cases', function () {
            // Quick transformer (low timeout)
            $quickTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Quick transformation';
                }

                protected function timeout(): ?int
                {
                    return 30; // 30 seconds for quick operations
                }
            };

            // Standard transformer (moderate timeout)
            $standardTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Standard transformation';
                }

                protected function timeout(): ?int
                {
                    return 180; // 3 minutes for standard operations
                }
            };

            // Long transformer (high timeout)
            $longTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Long transformation';
                }

                protected function timeout(): ?int
                {
                    return 600; // 10 minutes for complex operations
                }
            };

            $quickReflection = new ReflectionClass($quickTransformer);
            $quickMethod = $quickReflection->getMethod('timeout');
            $quickMethod->setAccessible(true);

            $standardReflection = new ReflectionClass($standardTransformer);
            $standardMethod = $standardReflection->getMethod('timeout');
            $standardMethod->setAccessible(true);

            $longReflection = new ReflectionClass($longTransformer);
            $longMethod = $longReflection->getMethod('timeout');
            $longMethod->setAccessible(true);

            expect($quickMethod->invoke($quickTransformer))->toBe(30);
            expect($standardMethod->invoke($standardTransformer))->toBe(180);
            expect($longMethod->invoke($longTransformer))->toBe(600);
        });

        test('can return null to use configuration defaults', function () {
            $defaultTimeoutTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Use configuration default timeout';
                }

                protected function timeout(): ?int
                {
                    return null; // Explicitly use configuration default
                }
            };

            $reflection = new ReflectionClass($defaultTimeoutTransformer);
            $timeoutMethod = $reflection->getMethod('timeout');
            $timeoutMethod->setAccessible(true);

            $timeout = $timeoutMethod->invoke($defaultTimeoutTransformer);

            expect($timeout)->toBeNull();
        });
    });

    describe('custom transformer connectTimeout() method overrides', function () {
        test('can override connectTimeout method with custom values', function () {
            $customTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform with custom connect timeout';
                }

                protected function connectTimeout(): ?int
                {
                    return 30; // 30 seconds for connection
                }
            };

            // Test the custom connectTimeout method using reflection
            $reflection = new ReflectionClass($customTransformer);
            $connectTimeoutMethod = $reflection->getMethod('connectTimeout');
            $connectTimeoutMethod->setAccessible(true);

            $connectTimeout = $connectTimeoutMethod->invoke($customTransformer);

            expect($connectTimeout)->toBe(30);
            expect($connectTimeout)->toBeInt();
        });

        test('can define different connectTimeout values for different networks', function () {
            // Fast network transformer (low connect timeout)
            $fastNetworkTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Fast network transformation';
                }

                protected function connectTimeout(): ?int
                {
                    return 5; // 5 seconds for fast networks
                }
            };

            // Standard network transformer (moderate connect timeout)
            $standardNetworkTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Standard network transformation';
                }

                protected function connectTimeout(): ?int
                {
                    return 10; // 10 seconds for standard networks
                }
            };

            // Slow network transformer (high connect timeout)
            $slowNetworkTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Slow network transformation';
                }

                protected function connectTimeout(): ?int
                {
                    return 30; // 30 seconds for slow networks
                }
            };

            $fastReflection = new ReflectionClass($fastNetworkTransformer);
            $fastMethod = $fastReflection->getMethod('connectTimeout');
            $fastMethod->setAccessible(true);

            $standardReflection = new ReflectionClass($standardNetworkTransformer);
            $standardMethod = $standardReflection->getMethod('connectTimeout');
            $standardMethod->setAccessible(true);

            $slowReflection = new ReflectionClass($slowNetworkTransformer);
            $slowMethod = $slowReflection->getMethod('connectTimeout');
            $slowMethod->setAccessible(true);

            expect($fastMethod->invoke($fastNetworkTransformer))->toBe(5);
            expect($standardMethod->invoke($standardNetworkTransformer))->toBe(10);
            expect($slowMethod->invoke($slowNetworkTransformer))->toBe(30);
        });
    });

    describe('timeout range validation scenarios', function () {
        test('supports valid timeout range values', function () {
            $validTimeouts = [
                1,      // Minimum practical value
                5,      // Very short timeout
                10,     // Short timeout
                30,     // Standard short timeout
                60,     // 1 minute
                180,    // 3 minutes (package default)
                300,    // 5 minutes
                600,    // 10 minutes
                1800,   // 30 minutes
                3600,   // 1 hour
            ];

            foreach ($validTimeouts as $timeout) {
                $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class), $timeout) extends BaseTransformer
                {
                    private int $testTimeout;

                    public function __construct(CacheManager $cache, ConfigurationService $configuration, ModelSchemaService $modelSchemaService, int $timeout)
                    {
                        parent::__construct($cache, $configuration, $modelSchemaService);
                        $this->testTimeout = $timeout;
                    }

                    public function prompt(): string
                    {
                        return 'Timeout range test';
                    }

                    protected function timeout(): ?int
                    {
                        return $this->testTimeout;
                    }
                };

                $reflection = new ReflectionClass($transformer);
                $timeoutMethod = $reflection->getMethod('timeout');
                $timeoutMethod->setAccessible(true);

                $result = $timeoutMethod->invoke($transformer);

                expect($result)->toBe($timeout);
                expect($result)->toBeInt();
                expect($result)->toBeGreaterThan(0);
            }
        });

        test('handles zero and negative timeout values gracefully', function () {
            // Note: This test documents that the BaseTransformer itself doesn't validate
            // timeout ranges - that's the responsibility of the HTTP client/Prism
            $invalidTimeoutTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Invalid timeout test';
                }

                protected function timeout(): ?int
                {
                    return 0; // Invalid, but BaseTransformer should not validate
                }
            };

            $reflection = new ReflectionClass($invalidTimeoutTransformer);
            $timeoutMethod = $reflection->getMethod('timeout');
            $timeoutMethod->setAccessible(true);

            // Should return the value as-is without validation
            $result = $timeoutMethod->invoke($invalidTimeoutTransformer);

            expect($result)->toBe(0);
            expect($result)->toBeInt();
        });
    });

    describe('combined timeout configuration', function () {
        test('can configure both timeout and connectTimeout independently', function () {
            $combinedTimeoutTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Combined timeout configuration';
                }

                protected function timeout(): ?int
                {
                    return 300; // 5 minutes for total request time
                }

                protected function connectTimeout(): ?int
                {
                    return 15; // 15 seconds for connection establishment
                }
            };

            $reflection = new ReflectionClass($combinedTimeoutTransformer);

            $timeoutMethod = $reflection->getMethod('timeout');
            $timeoutMethod->setAccessible(true);

            $connectTimeoutMethod = $reflection->getMethod('connectTimeout');
            $connectTimeoutMethod->setAccessible(true);

            $timeout = $timeoutMethod->invoke($combinedTimeoutTransformer);
            $connectTimeout = $connectTimeoutMethod->invoke($combinedTimeoutTransformer);

            expect($timeout)->toBe(300);
            expect($connectTimeout)->toBe(15);
            expect($timeout)->toBeGreaterThan($connectTimeout);
        });
    });
});
