<?php

declare(strict_types=1);

use Droath\PrismTransformer\PrismTransformer;
use Droath\PrismTransformer\Tests\Stubs\TestModel;
use Droath\PrismTransformer\Tests\Stubs\ProfileModel;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\Services\ModelSchemaService;
use Prism\Prism\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Illuminate\Database\Eloquent\Model;

describe('Complete Workflow Validation', function () {
    beforeEach(function () {
        $this->transformer = app(PrismTransformer::class);
        $this->modelSchemaService = app(ModelSchemaService::class);
        $this->cacheManager = $this->app->make(\Illuminate\Cache\CacheManager::class);
        $this->configurationService = $this->app->make(\Droath\PrismTransformer\Services\ConfigurationService::class);
    });

    describe('Full Pipeline Workflow Validation', function () {
        test('validates complete workflow: text → AI → schema → JSON → model', function () {
            // Step 1: Setup fake AI response
            $expectedData = [
                'name' => 'Workflow Test User',
                'email' => 'workflow@test.com',
                'age' => 29,
                'is_active' => true,
            ];

            $fakeResponse = StructuredResponseFake::make()
                ->withStructured($expectedData)
                ->withUsage(new Usage(20, 35));

            Prism::fake([$fakeResponse]);

            // Step 2: Create transformer with model output format
            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Extract user information and structure it according to the TestModel schema';
                }
            };

            // Step 3: Execute transformation with text input
            $inputText = 'Extract user: Workflow Test User, email workflow@test.com, age 29, active status';

            $result = $this->transformer
                ->text($inputText)
                ->using($transformer::class)
                ->transform();

            // Step 4: Validate transformation result
            expect($result)->toBeInstanceOf(TransformerResult::class);
            expect($result->isSuccessful())->toBeTrue();
            expect($result->getContent())->toBeString();

            $resultData = json_decode($result->getContent(), true);
            expect($resultData)->toBeArray();
            expect($resultData['name'])->toBe($expectedData['name']);
            expect($resultData['email'])->toBe($expectedData['email']);
            expect($resultData['age'])->toBe($expectedData['age']);
            expect($resultData['is_active'])->toBe($expectedData['is_active']);

            // Step 5: Validate schema was used in transformation
            $model = new TestModel();
            $schema = $this->modelSchemaService->convertModelToSchema($model);
            expect($schema)->not->toBeNull();

            // Step 6: Create model from result and validate
            $hydratedModel = $result->toModel(TestModel::class);
            expect($hydratedModel)->toBeInstanceOf(TestModel::class);
            expect($hydratedModel->name)->toBe($expectedData['name']);
            expect($hydratedModel->email)->toBe($expectedData['email']);
            expect($hydratedModel->age)->toBe($expectedData['age']);
            expect($hydratedModel->is_active)->toBe($expectedData['is_active']);

            // Step 7: Validate metadata preservation throughout workflow
            $metadata = $result->getMetadata();
            expect($metadata)->toBeInstanceOf(\Droath\PrismTransformer\ValueObjects\TransformerMetadata::class);
            expect($metadata->provider)->not->toBeNull();
            expect($metadata->timestamp)->not->toBeNull();
            expect($metadata->model)->not->toBeNull();
        });

        test('validates workflow with validation rules integration', function () {
            $expectedData = [
                'name' => 'Validated User',
                'email' => 'validated@example.com',
                'age' => 35,
                'is_active' => false,
            ];

            $fakeResponse = StructuredResponseFake::make()
                ->withStructured($expectedData)
                ->withUsage(new Usage(18, 28));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Extract and validate user data according to strict rules';
                }
            };

            // Execute transformation
            $result = $this->transformer
                ->text('User: Validated User, email validated@example.com, age 35, inactive')
                ->using($transformer::class)
                ->transform();

            // Define validation rules that should pass
            $validationRules = [
                'name' => 'required|string|min:3|max:50',
                'email' => 'required|email',
                'age' => 'required|integer|min:18|max:120',
                'is_active' => 'required|boolean',
            ];

            // Validate model creation with rules
            $model = $result->toModel(TestModel::class, $validationRules);
            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBe($expectedData['name']);
            expect($model->email)->toBe($expectedData['email']);
            expect($model->age)->toBe($expectedData['age']);
            expect($model->is_active)->toBe($expectedData['is_active']);
        });

        test('validates workflow with multiple model types in sequence', function () {
            // First workflow: TestModel
            $userData = [
                'name' => 'Sequential User',
                'email' => 'sequential@test.com',
                'age' => 28,
                'is_active' => true,
            ];

            $userResponse = StructuredResponseFake::make()
                ->withStructured($userData)
                ->withUsage(new Usage(15, 25));

            Prism::fake([$userResponse]);

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
                ->text('User: Sequential User, email sequential@test.com, age 28, active')
                ->using($userTransformer::class)
                ->transform();

            $user = $userResult->toModel(TestModel::class);
            expect($user->name)->toBe('Sequential User');
            expect($user->email)->toBe('sequential@test.com');

            // Second workflow: ProfileModel
            $profileData = [
                'bio' => 'Sequential testing profile',
                'website' => 'https://sequential.test',
                'age' => 28,
            ];

            $profileResponse = StructuredResponseFake::make()
                ->withStructured($profileData)
                ->withUsage(new Usage(12, 20));

            Prism::fake([$profileResponse]);

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
                ->text('Profile: Sequential testing profile, website https://sequential.test, age 28')
                ->using($profileTransformer::class)
                ->transform();

            $profile = $profileResult->toModel(ProfileModel::class);
            expect($profile->bio)->toBe('Sequential testing profile');
            expect($profile->website)->toBe('https://sequential.test');

            // Validate that both models are correctly created and independent
            expect($user)->toBeInstanceOf(TestModel::class);
            expect($profile)->toBeInstanceOf(ProfileModel::class);
            expect($user->name)->not->toBe($profile->bio);
        });

        test('validates workflow preserves transformation context and metadata', function () {
            $testData = [
                'name' => 'Context User',
                'email' => 'context@example.com',
                'age' => 42,
                'is_active' => true,
            ];

            $fakeResponse = StructuredResponseFake::make()
                ->withStructured($testData)
                ->withUsage(new Usage(25, 40));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Extract user data while preserving transformation context';
                }
            };

            $startTime = time();

            $result = $this->transformer
                ->text('Context preservation test: Context User, email context@example.com, age 42, active')
                ->using($transformer::class)
                ->transform();

            $endTime = time();

            // Validate transformation metadata
            $metadata = $result->getMetadata();
            expect($metadata)->toBeInstanceOf(\Droath\PrismTransformer\ValueObjects\TransformerMetadata::class);
            expect($metadata->provider)->not->toBeNull();
            expect($metadata->model)->not->toBeNull();
            expect($metadata->timestamp)->not->toBeNull();

            // Validate timing information is reasonable
            $timestamp = strtotime($metadata->timestamp);
            expect($timestamp)->toBeGreaterThanOrEqual($startTime);
            expect($timestamp)->toBeLessThanOrEqual($endTime);

            // Validate model creation preserves all context
            $model = $result->toModel(TestModel::class);
            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBe($testData['name']);

            // Verify that the result metadata is still accessible after model creation
            $metadataAfterModel = $result->getMetadata();
            expect($metadataAfterModel)->toBe($metadata);
        });

        test('validates workflow with async and chained transformations', function () {
            $userData = [
                'name' => 'Async User',
                'email' => 'async@example.com',
                'age' => 33,
                'is_active' => false,
            ];

            $fakeResponse = StructuredResponseFake::make()
                ->withStructured($userData)
                ->withUsage(new Usage(22, 38));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Process async transformation with model output';
                }
            };

            // Test workflow with method chaining
            $result = $this->transformer
                ->text('Async User data: Async User, email async@example.com, age 33, inactive')
                ->using($transformer::class)
                ->transform();

            expect($result)->toBeInstanceOf(TransformerResult::class);
            expect($result->isSuccessful())->toBeTrue();

            // Verify transformation can be chained with model creation
            $model = $result->toModel(TestModel::class);
            expect($model->name)->toBe('Async User');
            expect($model->email)->toBe('async@example.com');
            expect($model->age)->toBe(33);
            expect($model->is_active)->toBeFalse();

            // Verify the entire workflow is atomic and consistent
            expect($result->getContent())->toContain('async@example.com');
            expect($result->getMetadata()->provider)->not->toBeNull();
        });
    });

    describe('Cross-Component Integration Validation', function () {
        test('validates ModelSchemaService integration with BaseTransformer', function () {
            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Test schema service integration';
                }
            };

            // Test that schema service can work with transformer's outputFormat
            $model = new TestModel();
            $schema = $this->modelSchemaService->convertModelToSchema($model);

            expect($schema)->not->toBeNull();
            expect($schema->toArray()['type'])->toBe('object');

            // Test that schema reflects model structure
            $properties = $schema->toArray()['properties'];
            $fillableAttributes = $model->getFillable();

            foreach ($fillableAttributes as $attribute) {
                expect($properties)->toHaveKey($attribute);
            }
        });

        test('validates TransformerResult integration with ModelSchemaService', function () {
            $testData = [
                'name' => 'Integration Test',
                'email' => 'integration@test.com',
                'age' => 26,
                'is_active' => true,
            ];

            $fakeResponse = StructuredResponseFake::make()
                ->withStructured($testData)
                ->withUsage(new Usage(16, 24));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Test result service integration';
                }
            };

            $result = $this->transformer
                ->text('Integration Test data')
                ->using($transformer::class)
                ->transform();

            // Test that ModelSchemaService can handle the result's data
            $resultData = json_decode($result->getContent(), true);
            $model = $this->modelSchemaService->convertDataToModel(TestModel::class, $resultData);

            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBe($testData['name']);
            expect($model->email)->toBe($testData['email']);

            // Test that TransformerResult::toModel works with same data
            $resultModel = $result->toModel(TestModel::class);
            expect($resultModel->name)->toBe($model->name);
            expect($resultModel->email)->toBe($model->email);
        });

        test('validates service container integration throughout workflow', function () {
            // Verify all services are properly registered
            expect(app(PrismTransformer::class))->toBeInstanceOf(PrismTransformer::class);
            expect(app(ModelSchemaService::class))->toBeInstanceOf(ModelSchemaService::class);

            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['name' => 'Container User', 'email' => 'container@test.com', 'age' => 31])
                ->withUsage(new Usage(14, 22));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Test container integration';
                }
            };

            // Use transformer from container
            $containerTransformer = app(PrismTransformer::class);
            $result = $containerTransformer
                ->text('Container test')
                ->using($transformer::class)
                ->transform();

            $model = $result->toModel(TestModel::class);
            expect($model->name)->toBe('Container User');
            expect($model->email)->toBe('container@test.com');
        });
    });
})->group('integration', 'workflow-validation');
