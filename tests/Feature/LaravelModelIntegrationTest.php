<?php

declare(strict_types=1);

use Droath\PrismTransformer\PrismTransformer;
use Droath\PrismTransformer\Tests\Stubs\TestModel;
use Droath\PrismTransformer\Tests\Stubs\ProfileModel;
use Droath\PrismTransformer\Tests\Stubs\RelatedModel;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Prism\Prism\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Illuminate\Database\Eloquent\Model;

describe('Laravel Model Integration', function () {
    beforeEach(function () {
        $this->transformer = app(PrismTransformer::class);
        $this->cacheManager = $this->app->make(\Illuminate\Cache\CacheManager::class);
        $this->configurationService = $this->app->make(\Droath\PrismTransformer\Services\ConfigurationService::class);
    });

    describe('End-to-End PrismTransformer with Model Output', function () {
        test('transforms text to model instance using outputFormat', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30, 'is_active' => true])
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
                    return 'Extract user data and return as JSON';
                }
            };

            $result = $this->transformer
                ->text('Extract data from: John Doe is 30 years old, email john@example.com, and is currently active.')
                ->using($transformer::class)
                ->transform();

            expect($result)->toBeInstanceOf(TransformerResult::class);
            expect($result->isSuccessful())->toBeTrue();

            $model = $result->toModel(TestModel::class);
            expect($model)->toBeInstanceOf(TestModel::class);
            expect($model->name)->toBe('John Doe');
            expect($model->email)->toBe('john@example.com');
            expect($model->age)->toBe(30);
            expect($model->is_active)->toBeTrue();
        });

        test('handles complex model with nested data', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['name' => 'Jane Smith', 'email' => 'jane@example.com', 'age' => 25, 'is_active' => false])
                ->withUsage(new Usage(15, 25));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Extract user profile information as JSON';
                }
            };

            $result = $this->transformer
                ->text('Jane Smith, 25 years old, inactive user with email jane@example.com')
                ->using($transformer::class)
                ->transform();

            $model = $result->toModel(TestModel::class);
            expect($model->name)->toBe('Jane Smith');
            expect($model->email)->toBe('jane@example.com');
            expect($model->age)->toBe(25);
            expect($model->is_active)->toBeFalse();
        });

        test('works with different model types', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['bio' => 'Software developer', 'website' => 'https://example.com', 'age' => 28])
                ->withUsage(new Usage(12, 18));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new ProfileModel();
                }

                public function prompt(): string
                {
                    return 'Extract profile information as JSON';
                }
            };

            $result = $this->transformer
                ->text('Profile: Software developer, website https://example.com, 28 years old')
                ->using($transformer::class)
                ->transform();

            $profile = $result->toModel(ProfileModel::class);
            expect($profile)->toBeInstanceOf(ProfileModel::class);
            expect($profile->bio)->toBe('Software developer');
            expect($profile->website)->toBe('https://example.com');
            expect($profile->age)->toBe(28);
        });

        test('preserves transformation metadata with model creation', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['name' => 'Test User', 'email' => 'test@example.com', 'age' => 35])
                ->withUsage(new Usage(8, 16));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
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

            $result = $this->transformer
                ->text('User: Test User, email test@example.com, age 35')
                ->using($transformer::class)
                ->transform();

            // Check transformation metadata is preserved
            expect($result->getMetadata())->toBeInstanceOf(\Droath\PrismTransformer\ValueObjects\TransformerMetadata::class);
            expect($result->getMetadata()->provider)->not->toBeNull();
            expect($result->getMetadata()->timestamp)->not->toBeNull();

            // Check model creation still works
            $model = $result->toModel(TestModel::class);
            expect($model->name)->toBe('Test User');
            expect($model->email)->toBe('test@example.com');
            expect($model->age)->toBe(35);
        });
    });

    describe('Complete Workflow Integration', function () {
        test('full pipeline: text input → AI transformation → schema generation → model hydration', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['name' => 'Alice Johnson', 'email' => 'alice@test.com', 'age' => 32, 'is_active' => true])
                ->withUsage(new Usage(20, 35));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Parse the text and extract user information in the required format';
                }
            };

            // Step 1: Text input
            $inputText = 'User profile: Alice Johnson, 32 years old, email alice@test.com, currently active';

            // Step 2: AI Transformation with schema generation
            $result = $this->transformer
                ->text($inputText)
                ->using($transformer::class)
                ->transform();

            // Step 3: Verify transformation success
            expect($result->isSuccessful())->toBeTrue();
            expect($result->getContent())->not->toBeNull();

            // Step 4: Model hydration
            $user = $result->toModel(TestModel::class);

            // Step 5: Verify complete pipeline success
            expect($user)->toBeInstanceOf(TestModel::class);
            expect($user->name)->toBe('Alice Johnson');
            expect($user->email)->toBe('alice@test.com');
            expect($user->age)->toBe(32);
            expect($user->is_active)->toBeTrue();
        });

        test('handles validation during model hydration', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['name' => 'Bob Wilson', 'email' => 'bob@example.com', 'age' => 45, 'is_active' => false])
                ->withUsage(new Usage(18, 30));

            Prism::fake([$fakeResponse]);

            $transformer = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Extract user data with validation requirements';
                }
            };

            $result = $this->transformer
                ->text('Bob Wilson, aged 45, email bob@example.com, inactive status')
                ->using($transformer::class)
                ->transform();

            // Test with validation rules
            $validationRules = [
                'name' => 'required|string|min:3',
                'email' => 'required|email',
                'age' => 'required|integer|min:18',
                'is_active' => 'required|boolean',
            ];

            $user = $result->toModel(TestModel::class, $validationRules);
            expect($user)->toBeInstanceOf(TestModel::class);
            expect($user->name)->toBe('Bob Wilson');
            expect($user->email)->toBe('bob@example.com');
            expect($user->age)->toBe(45);
            expect($user->is_active)->toBeFalse();
        });

        test('integrates with Laravel service container', function () {
            $transformer = app(PrismTransformer::class);
            expect($transformer)->toBeInstanceOf(PrismTransformer::class);

            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['name' => 'Service User', 'email' => 'service@example.com', 'age' => 28])
                ->withUsage(new Usage(10, 15));

            Prism::fake([$fakeResponse]);

            $transformerClass = new class($this->cacheManager, $this->configurationService, $this->app->make(\Droath\PrismTransformer\Services\ModelSchemaService::class)) extends \Droath\PrismTransformer\Abstract\BaseTransformer
            {
                protected function outputFormat(): Model
                {
                    return new TestModel();
                }

                public function prompt(): string
                {
                    return 'Extract user from service container test';
                }
            };

            $result = $transformer
                ->text('Service User, email service@example.com, 28 years old')
                ->using($transformerClass::class)
                ->transform();

            $user = $result->toModel(TestModel::class);
            expect($user->name)->toBe('Service User');
            expect($user->email)->toBe('service@example.com');
            expect($user->age)->toBe(28);
        });
    });

    describe('Multiple Model Types Integration', function () {
        test('works with different models in same application', function () {
            // Test with TestModel
            $fakeResponse1 = StructuredResponseFake::make()
                ->withStructured(['name' => 'User One', 'email' => 'user1@example.com', 'age' => 25])
                ->withUsage(new Usage(10, 15));

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
                ->text('User One, email user1@example.com, 25 years old')
                ->using($userTransformer::class)
                ->transform();

            $user = $userResult->toModel(TestModel::class);
            expect($user)->toBeInstanceOf(TestModel::class);
            expect($user->name)->toBe('User One');

            // Test with ProfileModel
            $fakeResponse2 = StructuredResponseFake::make()
                ->withStructured(['bio' => 'Profile description', 'website' => 'https://profile.com', 'age' => 30])
                ->withUsage(new Usage(12, 18));

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
                ->text('Profile: Profile description, website https://profile.com, age 30')
                ->using($profileTransformer::class)
                ->transform();

            $profile = $profileResult->toModel(ProfileModel::class);
            expect($profile)->toBeInstanceOf(ProfileModel::class);
            expect($profile->bio)->toBe('Profile description');
            expect($profile->website)->toBe('https://profile.com');
        });

        test('handles model type flexibility', function () {
            $fakeResponse = StructuredResponseFake::make()
                ->withStructured(['title' => 'Related Content', 'description' => 'Some description', 'priority' => 5])
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
                    return 'Extract related content data';
                }
            };

            $result = $this->transformer
                ->text('Related Content: Some description with priority 5')
                ->using($transformer::class)
                ->transform();

            $related = $result->toModel(RelatedModel::class);
            expect($related)->toBeInstanceOf(RelatedModel::class);
            expect($related->title)->toBe('Related Content');
            expect($related->description)->toBe('Some description');
            expect($related->priority)->toBe(5);
        });
    });
})->group('integration', 'laravel-models');
