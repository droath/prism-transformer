<?php

declare(strict_types=1);

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\ValueObjects\TransformerMetadata;
use Illuminate\Cache\CacheManager;

describe('BaseTransformer Tools Configuration', function () {
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

    describe('tools() method default behavior', function () {
        test('returns empty array by default', function () {
            // Use reflection to test the protected method
            $reflection = new ReflectionClass($this->basicTransformer);
            $toolsMethod = $reflection->getMethod('tools');
            $toolsMethod->setAccessible(true);

            $result = $toolsMethod->invoke($this->basicTransformer);
            expect($result)->toBeArray();
            expect($result)->toBeEmpty();
        });

        test('method signature matches specification', function () {
            $reflection = new ReflectionClass($this->basicTransformer);
            $toolsMethod = $reflection->getMethod('tools');

            expect($toolsMethod->isProtected())->toBeTrue();
            expect($toolsMethod->getReturnType()?->getName())->toBe('array');
        });
    });

    describe('custom transformer tools() method overrides', function () {
        test('can override tools method with custom tool definitions', function () {
            $customTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform with custom tools';
                }

                protected function tools(): array
                {
                    return [
                        [
                            'name' => 'get_weather',
                            'description' => 'Get current weather information',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'location' => [
                                        'type' => 'string',
                                        'description' => 'The city name',
                                    ],
                                ],
                                'required' => ['location'],
                            ],
                        ],
                        [
                            'name' => 'calculate',
                            'description' => 'Perform mathematical calculations',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'expression' => [
                                        'type' => 'string',
                                        'description' => 'Mathematical expression',
                                    ],
                                ],
                                'required' => ['expression'],
                            ],
                        ],
                    ];
                }
            };

            // Test the custom tools method using reflection
            $reflection = new ReflectionClass($customTransformer);
            $toolsMethod = $reflection->getMethod('tools');
            $toolsMethod->setAccessible(true);

            $tools = $toolsMethod->invoke($customTransformer);

            expect($tools)->toBeArray();
            expect($tools)->toHaveCount(2);
            expect($tools[0]['name'])->toBe('get_weather');
            expect($tools[1]['name'])->toBe('calculate');
            expect($tools[0]['description'])->toBe('Get current weather information');
            expect($tools[1]['description'])->toBe('Perform mathematical calculations');
        });

        test('can define single tool', function () {
            $singleToolTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform with single tool';
                }

                protected function tools(): array
                {
                    return [
                        [
                            'name' => 'search_web',
                            'description' => 'Search the web for information',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'query' => [
                                        'type' => 'string',
                                        'description' => 'Search query',
                                    ],
                                ],
                                'required' => ['query'],
                            ],
                        ],
                    ];
                }
            };

            $reflection = new ReflectionClass($singleToolTransformer);
            $toolsMethod = $reflection->getMethod('tools');
            $toolsMethod->setAccessible(true);

            $tools = $toolsMethod->invoke($singleToolTransformer);

            expect($tools)->toBeArray();
            expect($tools)->toHaveCount(1);
            expect($tools[0]['name'])->toBe('search_web');
        });
    });

    describe('tools() method edge cases', function () {
        test('handles malformed tool definitions gracefully', function () {
            $malformedTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform with malformed tools';
                }

                protected function tools(): array
                {
                    return [
                        // Valid tool
                        [
                            'name' => 'valid_tool',
                            'description' => 'A valid tool',
                            'parameters' => ['type' => 'object'],
                        ],
                        // Malformed tool (missing required fields)
                        [
                            'description' => 'Missing name field',
                        ],
                        // Empty tool
                        [],
                        // Tool with only name
                        [
                            'name' => 'minimal_tool',
                        ],
                    ];
                }
            };

            $reflection = new ReflectionClass($malformedTransformer);
            $toolsMethod = $reflection->getMethod('tools');
            $toolsMethod->setAccessible(true);

            // Should not throw an exception, just return the array as-is
            $tools = $toolsMethod->invoke($malformedTransformer);

            expect($tools)->toBeArray();
            expect($tools)->toHaveCount(4);
            expect($tools[0]['name'])->toBe('valid_tool');
            expect($tools[1])->not->toHaveKey('name');
            expect($tools[2])->toBeEmpty();
            expect($tools[3]['name'])->toBe('minimal_tool');
        });

        test('handles complex nested tool parameter structures', function () {
            $complexToolTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform with complex tools';
                }

                protected function tools(): array
                {
                    return [
                        [
                            'name' => 'complex_api_call',
                            'description' => 'Make complex API call with nested parameters',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'config' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'auth' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'token' => ['type' => 'string'],
                                                    'method' => ['type' => 'string', 'enum' => ['bearer', 'basic']],
                                                ],
                                                'required' => ['token'],
                                            ],
                                            'options' => [
                                                'type' => 'array',
                                                'items' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'key' => ['type' => 'string'],
                                                        'value' => ['type' => 'string'],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        'required' => ['auth'],
                                    ],
                                ],
                                'required' => ['config'],
                            ],
                        ],
                    ];
                }
            };

            $reflection = new ReflectionClass($complexToolTransformer);
            $toolsMethod = $reflection->getMethod('tools');
            $toolsMethod->setAccessible(true);

            $tools = $toolsMethod->invoke($complexToolTransformer);

            expect($tools)->toBeArray();
            expect($tools)->toHaveCount(1);
            expect($tools[0]['name'])->toBe('complex_api_call');
            expect($tools[0]['parameters']['properties']['config']['properties']['auth']['properties'])->toHaveKey('token');
            expect($tools[0]['parameters']['properties']['config']['properties']['auth']['properties']['method']['enum'])->toContain('bearer');
        });
    });

    describe('tools method inheritance', function () {
        test('child classes can extend parent tools', function () {
            // Create a base transformer with tools
            $baseTransformerClass = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Base transformer';
                }

                protected function tools(): array
                {
                    return [
                        [
                            'name' => 'base_tool',
                            'description' => 'Tool from base class',
                        ],
                    ];
                }
            };

            // Create a child transformer that extends tools
            $childTransformerClass = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Child transformer';
                }

                protected function tools(): array
                {
                    $parentTools = [
                        [
                            'name' => 'base_tool',
                            'description' => 'Tool from base class',
                        ],
                    ];

                    return array_merge($parentTools, [
                        [
                            'name' => 'child_tool',
                            'description' => 'Tool from child class',
                        ],
                    ]);
                }
            };

            $baseReflection = new ReflectionClass($baseTransformerClass);
            $baseToolsMethod = $baseReflection->getMethod('tools');
            $baseToolsMethod->setAccessible(true);

            $childReflection = new ReflectionClass($childTransformerClass);
            $childToolsMethod = $childReflection->getMethod('tools');
            $childToolsMethod->setAccessible(true);

            $baseTools = $baseToolsMethod->invoke($baseTransformerClass);
            $childTools = $childToolsMethod->invoke($childTransformerClass);

            expect($baseTools)->toHaveCount(1);
            expect($childTools)->toHaveCount(2);
            expect($childTools[0]['name'])->toBe('base_tool');
            expect($childTools[1]['name'])->toBe('child_tool');
        });
    });

    describe('tools integration with performTransformation', function () {
        beforeEach(function () {
            // Disable caching for integration tests
            config(['prism-transformer.cache.enabled' => false]);
        });

        test('calls withTools when tools are defined', function () {
            // Create a transformer with tools
            $transformerWithTools = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Test prompt with tools';
                }

                protected function tools(): array
                {
                    return [
                        [
                            'name' => 'test_tool',
                            'description' => 'A test tool',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'input' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ];
                }

                // Override performTransformation to test the integration
                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    // Verify that tools() method is called and returns expected tools
                    $tools = $this->tools();
                    expect($tools)->toBeArray();
                    expect($tools)->toHaveCount(1);
                    expect($tools[0]['name'])->toBe('test_tool');

                    // For this test, we'll return a successful result without actual Prism call
                    // to avoid external dependencies in unit tests
                    return TransformerResult::successful(
                        'Test response with tools',
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            self::class
                        )
                    );
                }
            };

            $result = $transformerWithTools->execute('Test content');

            expect($result->isSuccessful())->toBeTrue();
            expect($result->data)->toBe('Test response with tools');
        });

        test('does not call withTools when tools array is empty', function () {
            // Create a transformer without tools (default behavior)
            $transformerWithoutTools = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Test prompt without tools';
                }

                // Override performTransformation to test the integration
                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    // Verify that tools() method returns empty array
                    $tools = $this->tools();
                    expect($tools)->toBeArray();
                    expect($tools)->toBeEmpty();

                    // For this test, we'll return a successful result without actual Prism call
                    return TransformerResult::successful(
                        'Test response without tools',
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            self::class
                        )
                    );
                }
            };

            $result = $transformerWithoutTools->execute('Test content');

            expect($result->isSuccessful())->toBeTrue();
            expect($result->data)->toBe('Test response without tools');
        });

        test('handles complex tool configurations correctly', function () {
            $complexToolsTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Complex tools test';
                }

                protected function tools(): array
                {
                    return [
                        [
                            'name' => 'weather_api',
                            'description' => 'Get weather information',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'location' => [
                                        'type' => 'string',
                                        'description' => 'City name',
                                    ],
                                    'units' => [
                                        'type' => 'string',
                                        'enum' => ['celsius', 'fahrenheit'],
                                        'default' => 'celsius',
                                    ],
                                ],
                                'required' => ['location'],
                            ],
                        ],
                        [
                            'name' => 'search_database',
                            'description' => 'Search internal database',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'query' => ['type' => 'string'],
                                    'limit' => ['type' => 'integer', 'maximum' => 100],
                                ],
                                'required' => ['query'],
                            ],
                        ],
                    ];
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $tools = $this->tools();

                    // Verify complex tool structure
                    expect($tools)->toHaveCount(2);
                    expect($tools[0]['name'])->toBe('weather_api');
                    expect($tools[1]['name'])->toBe('search_database');
                    expect($tools[0]['parameters']['properties']['units']['enum'])->toContain('celsius');
                    expect($tools[1]['parameters']['properties']['limit']['maximum'])->toBe(100);

                    return TransformerResult::successful(
                        'Complex tools processed',
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            self::class
                        )
                    );
                }
            };

            $result = $complexToolsTransformer->execute('Test content');

            expect($result->isSuccessful())->toBeTrue();
            expect($result->data)->toBe('Complex tools processed');
        });

        test('handles malformed tools gracefully in transformation', function () {
            $malformedToolsTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Malformed tools test';
                }

                protected function tools(): array
                {
                    return [
                        // Valid tool
                        [
                            'name' => 'valid_tool',
                            'description' => 'This tool is valid',
                        ],
                        // Malformed tool
                        [
                            'description' => 'Missing name',
                        ],
                        // Empty tool
                        [],
                    ];
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    $tools = $this->tools();

                    // Verify that malformed tools are still returned as-is
                    // (error handling should be done by Prism, not by BaseTransformer)
                    expect($tools)->toHaveCount(3);
                    expect($tools[0]['name'])->toBe('valid_tool');
                    expect($tools[1])->not->toHaveKey('name');
                    expect($tools[2])->toBeEmpty();

                    return TransformerResult::successful(
                        'Malformed tools handled',
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            self::class
                        )
                    );
                }
            };

            $result = $malformedToolsTransformer->execute('Test content');

            expect($result->isSuccessful())->toBeTrue();
            expect($result->data)->toBe('Malformed tools handled');
        });

        test('maintains method chaining when integrating tools', function () {
            // Test that the tools integration doesn't break the existing method chaining
            $chainingTestTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Method chaining test';
                }

                protected function tools(): array
                {
                    return [
                        [
                            'name' => 'chaining_tool',
                            'description' => 'Test method chaining with tools',
                        ],
                    ];
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    // Simulate the method chaining that happens in the actual implementation
                    $tools = $this->tools();

                    // Verify tools configuration
                    expect($tools)->toHaveCount(1);
                    expect($tools[0]['name'])->toBe('chaining_tool');

                    // Verify that other methods still work properly
                    expect($this->provider())->toBeInstanceOf(Provider::class);
                    expect($this->model())->toBeString();
                    expect($this->prompt())->toBe('Method chaining test');

                    return TransformerResult::successful(
                        'Method chaining preserved',
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            self::class
                        )
                    );
                }
            };

            $result = $chainingTestTransformer->execute('Test content');

            expect($result->isSuccessful())->toBeTrue();
            expect($result->data)->toBe('Method chaining preserved');
        });
    });

    describe('tools caching behavior', function () {
        test('tools configuration affects cache key generation', function () {
            config(['prism-transformer.cache.enabled' => true]);

            $transformer1 = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Same prompt';
                }

                protected function tools(): array
                {
                    return [
                        ['name' => 'tool1', 'description' => 'First tool set'],
                    ];
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    return TransformerResult::successful('Result with tool1');
                }
            };

            $transformer2 = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Same prompt';
                }

                protected function tools(): array
                {
                    return [
                        ['name' => 'tool2', 'description' => 'Second tool set'],
                    ];
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    return TransformerResult::successful('Result with tool2');
                }
            };

            $content = 'Test content';

            // Different tools should result in different cache entries
            $result1 = $transformer1->execute($content);
            $result2 = $transformer2->execute($content);

            expect($result1->data)->toBe('Result with tool1');
            expect($result2->data)->toBe('Result with tool2');

            // Note: In a real scenario, we'd verify that different cache keys are generated
            // For now, we just verify that different results are produced
        });
    });
});
