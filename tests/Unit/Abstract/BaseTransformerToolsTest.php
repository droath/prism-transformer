<?php

declare(strict_types=1);

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Services\ConfigurationService;
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
});
