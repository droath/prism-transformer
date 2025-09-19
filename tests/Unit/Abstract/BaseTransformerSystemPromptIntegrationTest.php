<?php

declare(strict_types=1);

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Services\ModelSchemaService;
use Illuminate\Cache\CacheManager;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

describe('BaseTransformer System Prompt Integration', function () {
    beforeEach(function () {
        // Create a concrete implementation for testing
        $this->basicTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
        {
            public function prompt(): string
            {
                return 'Transform this content';
            }
        };
    });

    describe('structureMessages integration', function () {
        test('includes system message when systemPrompt is defined', function () {
            $transformerWithSystemPrompt = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function systemPrompt(): ?string
                {
                    return 'You are a helpful assistant specialized in content transformation.';
                }
            };

            $reflection = new ReflectionClass($transformerWithSystemPrompt);
            $structureMessagesMethod = $reflection->getMethod('structureMessages');
            $structureMessagesMethod->setAccessible(true);

            $messages = $structureMessagesMethod->invoke($transformerWithSystemPrompt, 'Test content');

            expect($messages)->toHaveCount(3)
                ->and($messages[0])->toBeInstanceOf(SystemMessage::class)
                ->and($messages[1])->toBeInstanceOf(UserMessage::class)
                ->and($messages[2])->toBeInstanceOf(UserMessage::class);

            expect($messages[0]->content)->toBe('You are a helpful assistant specialized in content transformation.')
                ->and($messages[1]->text())->toBe('Transform this content')
                ->and($messages[2]->text())->toBe('Test content');
        });

        test('excludes system message when systemPrompt returns null', function () {
            $transformerWithoutSystemPrompt = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function systemPrompt(): ?string
                {
                    return null;
                }
            };

            $reflection = new ReflectionClass($transformerWithoutSystemPrompt);
            $structureMessagesMethod = $reflection->getMethod('structureMessages');
            $structureMessagesMethod->setAccessible(true);

            $messages = $structureMessagesMethod->invoke($transformerWithoutSystemPrompt, 'Test content');

            expect($messages)->toHaveCount(2)
                ->and($messages[0])->toBeInstanceOf(UserMessage::class)
                ->and($messages[1])->toBeInstanceOf(UserMessage::class);

            expect($messages[0]->text())->toBe('Transform this content')
                ->and($messages[1]->text())->toBe('Test content');
        });

        test('excludes system message when systemPrompt returns empty string', function () {
            $transformerWithEmptySystemPrompt = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function systemPrompt(): ?string
                {
                    return '';
                }
            };

            $reflection = new ReflectionClass($transformerWithEmptySystemPrompt);
            $structureMessagesMethod = $reflection->getMethod('structureMessages');
            $structureMessagesMethod->setAccessible(true);

            $messages = $structureMessagesMethod->invoke($transformerWithEmptySystemPrompt, 'Test content');

            // Empty string is truthy, so it should still be excluded
            expect($messages)->toHaveCount(2)
                ->and($messages[0])->toBeInstanceOf(UserMessage::class)
                ->and($messages[1])->toBeInstanceOf(UserMessage::class);

            expect($messages[0]->text())->toBe('Transform this content')
                ->and($messages[1]->text())->toBe('Test content');
        });

        test('maintains message order with system prompt first', function () {
            $transformerWithSystemPrompt = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Custom prompt text';
                }

                protected function systemPrompt(): ?string
                {
                    return 'System instructions for the model';
                }
            };

            $reflection = new ReflectionClass($transformerWithSystemPrompt);
            $structureMessagesMethod = $reflection->getMethod('structureMessages');
            $structureMessagesMethod->setAccessible(true);

            $messages = $structureMessagesMethod->invoke($transformerWithSystemPrompt, 'User content to transform');

            expect($messages)->toHaveCount(3);

            // Verify message order and content
            expect($messages[0])->toBeInstanceOf(SystemMessage::class)
                ->and($messages[0]->content)->toBe('System instructions for the model');

            expect($messages[1])->toBeInstanceOf(UserMessage::class)
                ->and($messages[1]->text())->toBe('Custom prompt text');

            expect($messages[2])->toBeInstanceOf(UserMessage::class)
                ->and($messages[2]->text())->toBe('User content to transform');
        });
    });

    describe('system prompt caching behavior', function () {
        test('system prompt contributes to cache key generation', function () {
            $transformerWithSystemPrompt = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function systemPrompt(): ?string
                {
                    return 'System prompt for caching';
                }
            };

            $transformerWithoutSystemPrompt = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function systemPrompt(): ?string
                {
                    return null;
                }
            };

            $reflectionWith = new ReflectionClass($transformerWithSystemPrompt);
            $cacheIdMethodWith = $reflectionWith->getMethod('cacheId');
            $cacheIdMethodWith->setAccessible(true);

            $reflectionWithout = new ReflectionClass($transformerWithoutSystemPrompt);
            $cacheIdMethodWithout = $reflectionWithout->getMethod('cacheId');
            $cacheIdMethodWithout->setAccessible(true);

            $cacheIdWith = $cacheIdMethodWith->invoke($transformerWithSystemPrompt);
            $cacheIdWithout = $cacheIdMethodWithout->invoke($transformerWithoutSystemPrompt);

            // Different system prompts should generate different cache keys
            expect($cacheIdWith)->not->toBe($cacheIdWithout);
        });

        test('different system prompts generate different cache keys', function () {
            $transformer1 = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function systemPrompt(): ?string
                {
                    return 'First system prompt';
                }
            };

            $transformer2 = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function systemPrompt(): ?string
                {
                    return 'Second system prompt';
                }
            };

            $reflection1 = new ReflectionClass($transformer1);
            $cacheIdMethod1 = $reflection1->getMethod('cacheId');
            $cacheIdMethod1->setAccessible(true);

            $reflection2 = new ReflectionClass($transformer2);
            $cacheIdMethod2 = $reflection2->getMethod('cacheId');
            $cacheIdMethod2->setAccessible(true);

            $cacheId1 = $cacheIdMethod1->invoke($transformer1);
            $cacheId2 = $cacheIdMethod2->invoke($transformer2);

            expect($cacheId1)->not->toBe($cacheId2);
        });
    });
});
