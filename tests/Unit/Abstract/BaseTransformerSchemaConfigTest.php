<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Tests\Unit\Abstract;

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Tests\Stubs\TestModel;
use Illuminate\Cache\CacheManager;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Services\ModelSchemaService;
use Prism\Prism\Schema\ObjectSchema;

describe('BaseTransformer schema configuration integration', function () {
    beforeEach(function () {
        $this->cache = mock(CacheManager::class);
        $this->config = mock(ConfigurationService::class);
        $this->modelSchemaService = mock(ModelSchemaService::class);
    });

    describe('getModelSchemaConfig method', function () {
        test('returns empty array by default', function () {
            $transformer = new class($this->cache, $this->config, $this->modelSchemaService) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'test';
                }

                public function getSchemaConfig(): array
                {
                    return $this->getModelSchemaConfig();
                }
            };

            expect($transformer->getSchemaConfig())->toBe([]);
        });

        test('can be overridden by concrete transformers', function () {
            $transformer = new class($this->cache, $this->config, $this->modelSchemaService) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'test';
                }

                public function getSchemaConfig(): array
                {
                    return $this->getModelSchemaConfig();
                }

                protected function getModelSchemaConfig(): array
                {
                    return [
                        'name' => ['required' => true],
                        'email' => ['required' => true],
                        'age' => ['required' => false, 'type' => 'integer'],
                    ];
                }
            };

            $expected = [
                'name' => ['required' => true],
                'email' => ['required' => true],
                'age' => ['required' => false, 'type' => 'integer'],
            ];

            expect($transformer->getSchemaConfig())->toBe($expected);
        });
    });

    describe('resolveOutputFormat integration', function () {
        test('passes schema configuration to ModelSchemaService', function () {
            $model = new TestModel();
            $expectedSchema = mock(ObjectSchema::class);
            $expectedConfig = [
                'name' => ['required' => true],
                'age' => ['required' => false],
            ];

            $this->modelSchemaService
                ->shouldReceive('convertModelToSchema')
                ->once()
                ->with($model, $expectedConfig)
                ->andReturn($expectedSchema);

            $transformer = new class($this->cache, $this->config, $this->modelSchemaService) extends BaseTransformer
            {
                private $model;

                private $config;

                public function prompt(): string
                {
                    return 'test';
                }

                public function setTestData($model, $config): void
                {
                    $this->model = $model;
                    $this->config = $config;
                }

                protected function outputFormat(): null|\Prism\Prism\Schema\ObjectSchema|\Illuminate\Database\Eloquent\Model
                {
                    return $this->model;
                }

                protected function getModelSchemaConfig(): array
                {
                    return $this->config;
                }

                public function testResolveOutputFormat(): ?\Prism\Prism\Schema\ObjectSchema
                {
                    return $this->resolveOutputFormat();
                }
            };

            $transformer->setTestData($model, $expectedConfig);
            $result = $transformer->testResolveOutputFormat();

            expect($result)->toBe($expectedSchema);
        });

        test('passes empty config when getModelSchemaConfig returns empty array', function () {
            $model = new TestModel();
            $expectedSchema = mock(ObjectSchema::class);

            $this->modelSchemaService
                ->shouldReceive('convertModelToSchema')
                ->once()
                ->with($model, [])
                ->andReturn($expectedSchema);

            $transformer = new class($this->cache, $this->config, $this->modelSchemaService) extends BaseTransformer
            {
                private $model;

                public function prompt(): string
                {
                    return 'test';
                }

                public function setModel($model): void
                {
                    $this->model = $model;
                }

                protected function outputFormat(): null|\Prism\Prism\Schema\ObjectSchema|\Illuminate\Database\Eloquent\Model
                {
                    return $this->model;
                }

                public function testResolveOutputFormat(): ?\Prism\Prism\Schema\ObjectSchema
                {
                    return $this->resolveOutputFormat();
                }
            };

            $transformer->setModel($model);
            $result = $transformer->testResolveOutputFormat();

            expect($result)->toBe($expectedSchema);
        });

        test('does not call ModelSchemaService for ObjectSchema output format', function () {
            $schema = mock(ObjectSchema::class);

            $this->modelSchemaService
                ->shouldNotReceive('convertModelToSchema');

            $transformer = new class($this->cache, $this->config, $this->modelSchemaService) extends BaseTransformer
            {
                private $schema;

                public function prompt(): string
                {
                    return 'test';
                }

                public function setSchema($schema): void
                {
                    $this->schema = $schema;
                }

                protected function outputFormat(): null|\Prism\Prism\Schema\ObjectSchema|\Illuminate\Database\Eloquent\Model
                {
                    return $this->schema;
                }

                public function testResolveOutputFormat(): ?\Prism\Prism\Schema\ObjectSchema
                {
                    return $this->resolveOutputFormat();
                }
            };

            $transformer->setSchema($schema);
            $result = $transformer->testResolveOutputFormat();

            expect($result)->toBe($schema);
        });

        test('returns null for null output format', function () {
            $this->modelSchemaService
                ->shouldNotReceive('convertModelToSchema');

            $transformer = new class($this->cache, $this->config, $this->modelSchemaService) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'test';
                }

                protected function outputFormat(): null|\Prism\Prism\Schema\ObjectSchema|\Illuminate\Database\Eloquent\Model
                {
                    return null;
                }

                public function testResolveOutputFormat(): ?\Prism\Prism\Schema\ObjectSchema
                {
                    return $this->resolveOutputFormat();
                }
            };

            $result = $transformer->testResolveOutputFormat();

            expect($result)->toBeNull();
        });
    });
});
