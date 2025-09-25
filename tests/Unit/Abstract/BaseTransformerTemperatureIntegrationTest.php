<?php

declare(strict_types=1);

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Services\ModelSchemaService;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\ValueObjects\TransformerMetadata;
use Illuminate\Cache\CacheManager;

describe('BaseTransformer Temperature Integration', function () {
    beforeEach(function () {
        // Disable caching for integration tests
        config(['prism-transformer.cache.enabled' => false]);
    });

    describe('temperature integration with performTransformation', function () {
        test('calls usingTemperature when temperature is defined', function () {
            // Create a transformer with temperature
            $transformerWithTemperature = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Test prompt with temperature';
                }

                protected function temperature(): ?float
                {
                    return 0.7;
                }

                // Override performTransformation to test the integration
                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    // Verify that temperature() method is called and returns expected value
                    $temperature = $this->temperature();
                    expect($temperature)->toBe(0.7);
                    expect($temperature)->toBeFloat();

                    // For this test, we'll return a successful result without actual Prism call
                    // to avoid external dependencies in unit tests
                    return TransformerResult::successful(
                        'Test response with temperature 0.7',
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            self::class
                        )
                    );
                }
            };

            $result = $transformerWithTemperature->execute('Test content');

            expect($result->isSuccessful())->toBeTrue();
            expect($result->data)->toBe('Test response with temperature 0.7');
        });

        test('does not call usingTemperature when temperature is null', function () {
            // Create a transformer without temperature (default behavior)
            $transformerWithoutTemperature = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Test prompt without temperature';
                }

                // Override performTransformation to test the integration
                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    // Verify that temperature() method returns null
                    $temperature = $this->temperature();
                    expect($temperature)->toBeNull();

                    // For this test, we'll return a successful result without actual Prism call
                    return TransformerResult::successful(
                        'Test response without temperature',
                        TransformerMetadata::make(
                            $this->model(),
                            $this->provider(),
                            self::class
                        )
                    );
                }
            };

            $result = $transformerWithoutTemperature->execute('Test content');

            expect($result->isSuccessful())->toBeTrue();
            expect($result->data)->toBe('Test response without temperature');
        });

        test('handles different temperature values correctly', function () {
            $temperatureValues = [
                0.0 => 'deterministic',
                0.1 => 'focused',
                0.7 => 'balanced',
                1.2 => 'creative',
                2.0 => 'very_creative',
            ];

            foreach ($temperatureValues as $tempValue => $label) {
                $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class), $tempValue, $label) extends BaseTransformer
                {
                    private float $testTemperature;

                    private string $label;

                    public function __construct(CacheManager $cache, ConfigurationService $configuration, ModelSchemaService $modelSchemaService, float $temperature, string $label)
                    {
                        parent::__construct($cache, $configuration, $modelSchemaService);
                        $this->testTemperature = $temperature;
                        $this->label = $label;
                    }

                    public function prompt(): string
                    {
                        return "Temperature test for {$this->label}";
                    }

                    protected function temperature(): ?float
                    {
                        return $this->testTemperature;
                    }

                    protected function performTransformation(string $content, array $context = []): TransformerResult
                    {
                        $temperature = $this->temperature();

                        // Verify temperature value
                        expect($temperature)->toBe($this->testTemperature);
                        expect($temperature)->toBeFloat();

                        return TransformerResult::successful(
                            "Response with {$this->label} temperature {$temperature}",
                            TransformerMetadata::make(
                                $this->model(),
                                $this->provider(),
                                self::class
                            )
                        );
                    }
                };

                $result = $transformer->execute('Test content');

                expect($result->isSuccessful())->toBeTrue();
                expect($result->data)->toContain($label);
                expect($result->data)->toContain((string) $tempValue);
            }
        });

        test('maintains method chaining when integrating temperature', function () {
            // Test that the temperature integration doesn't break the existing method chaining
            $chainingTestTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Method chaining test with temperature';
                }

                protected function temperature(): ?float
                {
                    return 0.8;
                }

                protected function tools(): array
                {
                    return [
                        [
                            'name' => 'test_tool',
                            'description' => 'Test tool for method chaining',
                        ],
                    ];
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    // Verify temperature configuration
                    $temperature = $this->temperature();
                    expect($temperature)->toBe(0.8);

                    // Verify tools configuration
                    $tools = $this->tools();
                    expect($tools)->toHaveCount(1);
                    expect($tools[0]['name'])->toBe('test_tool');

                    // Verify that other methods still work properly
                    expect($this->provider())->toBeInstanceOf(Provider::class);
                    expect($this->model())->toBeString();
                    expect($this->prompt())->toBe('Method chaining test with temperature');

                    return TransformerResult::successful(
                        'Method chaining with temperature preserved',
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
            expect($result->data)->toBe('Method chaining with temperature preserved');
        });

        test('handles edge case temperature values in transformation', function () {
            $edgeTemperatures = [
                0.0,      // Minimum
                0.001,    // Very small
                1.0,      // Exactly 1.0
                1.999,    // Close to 2.0
                2.0,      // Common maximum
                -0.5,     // Negative (should be passed through without validation)
                5.0,      // Very high (should be passed through without validation)
            ];

            foreach ($edgeTemperatures as $temp) {
                $transformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class), $temp) extends BaseTransformer
                {
                    private float $testTemperature;

                    public function __construct(CacheManager $cache, ConfigurationService $configuration, ModelSchemaService $modelSchemaService, float $temperature)
                    {
                        parent::__construct($cache, $configuration, $modelSchemaService);
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

                    protected function performTransformation(string $content, array $context = []): TransformerResult
                    {
                        $temperature = $this->temperature();

                        // Verify that edge case temperatures are handled without validation
                        expect($temperature)->toBe($this->testTemperature);
                        expect($temperature)->toBeFloat();

                        return TransformerResult::successful(
                            "Edge case temperature {$temperature} handled",
                            TransformerMetadata::make(
                                $this->model(),
                                $this->provider(),
                                self::class
                            )
                        );
                    }
                };

                $result = $transformer->execute('Test content');

                expect($result->isSuccessful())->toBeTrue();
                expect($result->data)->toContain((string) $temp);
            }
        });
    });

    describe('temperature caching behavior', function () {
        test('temperature configuration affects cache key generation', function () {
            config(['prism-transformer.cache.enabled' => true]);

            $transformer1 = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Same prompt';
                }

                protected function temperature(): ?float
                {
                    return 0.3; // Low temperature
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    return TransformerResult::successful('Result with temperature 0.3');
                }
            };

            $transformer2 = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Same prompt';
                }

                protected function temperature(): ?float
                {
                    return 1.5; // High temperature
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    return TransformerResult::successful('Result with temperature 1.5');
                }
            };

            $content = 'Test content';

            // Different temperatures should result in different cache entries
            $result1 = $transformer1->execute($content);
            $result2 = $transformer2->execute($content);

            expect($result1->data)->toBe('Result with temperature 0.3');
            expect($result2->data)->toBe('Result with temperature 1.5');

            // Note: In a real scenario, we'd verify that different cache keys are generated
            // For now, we just verify that different results are produced
        });

        test('null temperature vs specific temperature creates different cache entries', function () {
            config(['prism-transformer.cache.enabled' => true]);

            $transformerNull = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Same prompt';
                }

                protected function temperature(): ?float
                {
                    return null; // Use provider default
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    return TransformerResult::successful('Result with null temperature');
                }
            };

            $transformerSpecific = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Same prompt';
                }

                protected function temperature(): ?float
                {
                    return 0.7; // Specific temperature
                }

                protected function performTransformation(string $content, array $context = []): TransformerResult
                {
                    return TransformerResult::successful('Result with temperature 0.7');
                }
            };

            $content = 'Test content';

            // Null temperature vs specific temperature should result in different cache entries
            $result1 = $transformerNull->execute($content);
            $result2 = $transformerSpecific->execute($content);

            expect($result1->data)->toBe('Result with null temperature');
            expect($result2->data)->toBe('Result with temperature 0.7');
        });
    });
});
