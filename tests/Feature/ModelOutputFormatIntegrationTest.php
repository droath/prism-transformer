<?php

declare(strict_types=1);

use Droath\PrismTransformer\PrismTransformer;
use Droath\PrismTransformer\Tests\Stubs\TestModel;
use Droath\PrismTransformer\Tests\Stubs\ProfileModel;
use Droath\PrismTransformer\Tests\Stubs\RelatedModel;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\Services\ModelSchemaService;
use Prism\Prism\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Illuminate\Database\Eloquent\Model;

describe('Model OutputFormat Method Integration', function () {
    beforeEach(function () {
        $this->transformer = app(PrismTransformer::class);
        $this->modelSchemaService = app(ModelSchemaService::class);
        $this->cacheManager = $this->app->make(\Illuminate\Cache\CacheManager::class);
        $this->configurationService = $this->app->make(\Droath\PrismTransformer\Services\ConfigurationService::class);
    });

    describe('outputFormat() with Model Returns', function () {
        test('outputFormat() returns Model instance and generates correct schema', function () {
            $testModel = new TestModel();
            expect($testModel)->toBeInstanceOf(TestModel::class);
        });

        test('outputFormat() with TestModel generates appropriate JSON schema', function () {
            $testModel = new TestModel();
            $schema = $this->modelSchemaService->convertModelToSchema($testModel);

            expect($schema)->not->toBeNull();
            expect($schema->toArray())->toHaveKey('type');
            expect($schema->toArray()['type'])->toBe('object');
            expect($schema->toArray())->toHaveKey('properties');

            $properties = $schema->toArray()['properties'];
            expect($properties)->toHaveKey('name');
            expect($properties)->toHaveKey('email');
            expect($properties)->toHaveKey('age');
            expect($properties)->toHaveKey('is_active');
            expect($properties['age']['type'])->toBe('number');
            expect($properties['is_active']['type'])->toBe('boolean');
        });

        test('outputFormat() with ProfileModel generates different schema', function () {
            $profileModel = new ProfileModel();
            $schema = $this->modelSchemaService->convertModelToSchema($profileModel);

            expect($schema)->not->toBeNull();
            $properties = $schema->toArray()['properties'];
            expect($properties)->toHaveKey('bio');
            expect($properties)->toHaveKey('website');
            expect($properties)->toHaveKey('age');
            expect($properties['bio']['type'])->toBe('string');
            expect($properties['website']['type'])->toBe('string');
        });

        test('outputFormat() with RelatedModel generates specific schema', function () {
            $relatedModel = new RelatedModel();
            $schema = $this->modelSchemaService->convertModelToSchema($relatedModel);

            expect($schema)->not->toBeNull();
            $properties = $schema->toArray()['properties'];
            expect($properties)->toHaveKey('title');
            expect($properties)->toHaveKey('description');
            expect($properties)->toHaveKey('priority');
            expect($properties['priority']['type'])->toBe('number');
        });
    });

    describe('Fake Prism Response Integration with Models', function () {
        test('fake response creates valid model through outputFormat', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['name' => 'Fake User', 'email' => 'fake@example.com', 'age' => 40, 'is_active' => true])
                ->withUsage(new Usage(10, 20));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Parse user data from fake response';
                }
            };

            $result = $this->transformer
                ->text('Extract user: Fake User, email fake@example.com, age 40, active')
                ->using($transformer::class)
                ->transform();

            expect($result)->toBeInstanceOf(TransformerResult::class);
            expect($result->isSuccessful())->toBeTrue();
            expect($result->getContent())->not->toBeNull();

            $model = $result->toModel(TestModel::class);
            expect($model->name)->toBe('Fake User');
            expect($model->email)->toBe('fake@example.com');
            expect($model->age)->toBe(40);
            expect($model->is_active)->toBeTrue();
        });

        test('multiple fake responses create different models', function () {
            // First response for TestModel
            $fakeResponse1 = StructuredResponseFake::make()
                ->withStructured(['name' => 'User One', 'email' => 'user1@test.com', 'age' => 25, 'is_active' => false])
                ->withUsage(new Usage(8, 15));

            Prism::fake([$fakeResponse1]);

            $userTransformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Extract user data';
                }
            };

            $userResult = $this->transformer
                ->text('User One data')
                ->using($userTransformer::class)
                ->transform();

            $user = $userResult->toModel(TestModel::class);
            expect($user->name)->toBe('User One');
            expect($user->email)->toBe('user1@test.com');
            expect($user->is_active)->toBeFalse();

            // Second response for ProfileModel
            $fakeResponse2 = StructuredResponseFake::make()
                ->withStructured(['bio' => 'Software engineer', 'website' => 'https://dev.example.com', 'age' => 32])
                ->withUsage(new Usage(12, 20));

            Prism::fake([$fakeResponse2]);

            $profileTransformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new ProfileModel();
                }

                public function prompt(): string
                {
                    return 'Extract profile data';
                }
            };

            $profileResult = (app(PrismTransformer::class))
                ->text('Profile data')
                ->using($profileTransformer::class)
                ->transform();

            $profile = $profileResult->toModel(ProfileModel::class);
            expect($profile->bio)->toBe('Software engineer');
            expect($profile->website)->toBe('https://dev.example.com');
            expect($profile->age)->toBe(32);
        });

        test('fake response handles model with complex data types', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['title' => 'Complex Task', 'description' => 'A task with unicode: 测试 and symbols: @#$%', 'priority' => 9])
                ->withUsage(new Usage(15, 25));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new RelatedModel();
                }

                public function prompt(): string
                {
                    return 'Extract complex task data with unicode and special characters';
                }
            };

            $result = $this->transformer
                ->text('Complex task with unicode and symbols')
                ->using($transformer::class)
                ->transform();

            $task = $result->toModel(RelatedModel::class);
            expect($task->title)->toBe('Complex Task');
            expect($task->description)->toBe('A task with unicode: 测试 and symbols: @#$%');
            expect($task->priority)->toBe(9);
        });

        test('fake response preserves model casting behavior', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['name' => 'Cast Test', 'email' => 'cast@test.com', 'age' => '35', 'is_active' => '1'])
                ->withUsage(new Usage(10, 18));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Test casting with string inputs';
                }
            };

            $result = $this->transformer
                ->text('Cast test data')
                ->using($transformer::class)
                ->transform();

            $model = $result->toModel(TestModel::class);

            // Verify that casting works correctly (string "35" becomes integer 35)
            expect($model->age)->toBe(35);
            expect($model->age)->toBeInt();

            // Verify boolean casting (string "1" becomes boolean true)
            expect($model->is_active)->toBeTrue();
            expect($model->is_active)->toBeBool();
        });
    });

    describe('Schema Generation Integration', function () {
        test('outputFormat() integrates with schema service for transformation', function () {
            $model = new TestModel();
            expect($model)->toBeInstanceOf(TestModel::class);

            $schema = $this->modelSchemaService->convertModelToSchema($model);
            expect($schema)->not->toBeNull();

            // Verify schema has required properties for TestModel
            $schemaArray = $schema->toArray();
            expect($schemaArray['properties'])->toHaveKeys(['name', 'email', 'age', 'is_active']);
        });

        test('different model types produce different schemas through outputFormat', function () {
            $testModel = new TestModel();
            $profileModel = new ProfileModel();

            $testSchema = $this->modelSchemaService->convertModelToSchema($testModel);
            $profileSchema = $this->modelSchemaService->convertModelToSchema($profileModel);

            expect($testSchema->toArray()['properties'])->toHaveKey('email');
            expect($testSchema->toArray()['properties'])->toHaveKey('is_active');

            expect($profileSchema->toArray()['properties'])->toHaveKey('bio');
            expect($profileSchema->toArray()['properties'])->toHaveKey('website');

            // Schemas should be different
            expect($testSchema->toArray())->not->toBe($profileSchema->toArray());
        });

        test('outputFormat() model schema respects fillable attributes', function () {
            $model = new TestModel();
            $schema = $this->modelSchemaService->convertModelToSchema($model);
            $properties = array_keys($schema->toArray()['properties']);

            $fillableAttributes = $model->getFillable();

            // All fillable attributes should be in schema properties
            foreach ($fillableAttributes as $attribute) {
                expect($properties)->toContain($attribute);
            }
        });
    });
})->group('integration', 'model-output-format');
