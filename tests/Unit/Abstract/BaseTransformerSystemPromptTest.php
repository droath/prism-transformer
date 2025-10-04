<?php

declare(strict_types=1);

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Services\ModelSchemaService;
use Illuminate\Cache\CacheManager;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

describe('BaseTransformer System Prompt Configuration', function () {
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

    describe('systemPrompt() method default behavior', function () {
        test('returns null by default', function () {
            // Use reflection to test the protected method
            $reflection = new ReflectionClass($this->basicTransformer);
            $systemPromptMethod = $reflection->getMethod('systemPrompt');
            $systemPromptMethod->setAccessible(true);

            $result = $systemPromptMethod->invoke($this->basicTransformer);
            expect($result)->toBeNull();
        });

        test('method signature matches specification', function () {
            $reflection = new ReflectionClass($this->basicTransformer);
            $systemPromptMethod = $reflection->getMethod('systemPrompt');

            expect($systemPromptMethod->isProtected())->toBeTrue();

            // Check if the return type allows null (nullable string)
            $returnType = $systemPromptMethod->getReturnType();
            expect($returnType)->not->toBeNull();
            expect($returnType->allowsNull())->toBeTrue();
            expect($returnType->getName())->toBe('string');
        });
    });

    describe('custom transformer systemPrompt() method overrides', function () {
        test('can override systemPrompt method with custom message', function () {
            $customTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
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

            $reflection = new ReflectionClass($customTransformer);
            $systemPromptMethod = $reflection->getMethod('systemPrompt');
            $systemPromptMethod->setAccessible(true);

            $result = $systemPromptMethod->invoke($customTransformer);
            expect($result)->toBe('You are a helpful assistant specialized in content transformation.');
        });

        test('can return null to use no system message', function () {
            $customTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
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

            $reflection = new ReflectionClass($customTransformer);
            $systemPromptMethod = $reflection->getMethod('systemPrompt');
            $systemPromptMethod->setAccessible(true);

            $result = $systemPromptMethod->invoke($customTransformer);
            expect($result)->toBeNull();
        });
    });

    describe('system prompt method inheritance', function () {
        test('child classes can override parent systemPrompt', function () {
            // Create named classes for inheritance testing
            $parentClass = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function systemPrompt(): ?string
                {
                    return 'Parent system message';
                }
            };

            $childClass = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function systemPrompt(): ?string
                {
                    return 'Child system message';
                }
            };

            $parentReflection = new ReflectionClass($parentClass);
            $parentSystemPromptMethod = $parentReflection->getMethod('systemPrompt');
            $parentSystemPromptMethod->setAccessible(true);

            $childReflection = new ReflectionClass($childClass);
            $childSystemPromptMethod = $childReflection->getMethod('systemPrompt');
            $childSystemPromptMethod->setAccessible(true);

            expect($parentSystemPromptMethod->invoke($parentClass))->toBe('Parent system message');
            expect($childSystemPromptMethod->invoke($childClass))->toBe('Child system message');
        });

        test('child classes can modify systemPrompt behavior', function () {
            $customTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                private string $baseInstructions = 'Base instructions';

                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function systemPrompt(): ?string
                {
                    return $this->baseInstructions.' with additional context';
                }
            };

            $reflection = new ReflectionClass($customTransformer);
            $systemPromptMethod = $reflection->getMethod('systemPrompt');
            $systemPromptMethod->setAccessible(true);

            expect($systemPromptMethod->invoke($customTransformer))->toBe('Base instructions with additional context');
        });
    });

    describe('system prompt edge cases', function () {
        test('handles empty string system prompt', function () {
            $emptyTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
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

            $reflection = new ReflectionClass($emptyTransformer);
            $systemPromptMethod = $reflection->getMethod('systemPrompt');
            $systemPromptMethod->setAccessible(true);

            $result = $systemPromptMethod->invoke($emptyTransformer);
            expect($result)->toBe('');
        });

        test('handles multi-line system prompt', function () {
            $multilineTransformer = new class($this->app->make(CacheManager::class), $this->app->make(ConfigurationService::class), $this->app->make(ModelSchemaService::class)) extends BaseTransformer
            {
                public function prompt(): string
                {
                    return 'Transform this content';
                }

                protected function systemPrompt(): ?string
                {
                    return "You are a helpful assistant.\nBe concise and accurate.\nFocus on key information.";
                }
            };

            $reflection = new ReflectionClass($multilineTransformer);
            $systemPromptMethod = $reflection->getMethod('systemPrompt');
            $systemPromptMethod->setAccessible(true);

            $result = $systemPromptMethod->invoke($multilineTransformer);
            expect($result)->toBe("You are a helpful assistant.\nBe concise and accurate.\nFocus on key information.");
        });
    });

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

                $cacheIdWith = $cacheIdMethodWith->invoke($transformerWithSystemPrompt, 'test content', []);
                $cacheIdWithout = $cacheIdMethodWithout->invoke($transformerWithoutSystemPrompt, 'test content', []);

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

                $cacheId1 = $cacheIdMethod1->invoke($transformer1, 'test content', []);
                $cacheId2 = $cacheIdMethod2->invoke($transformer2, 'test content', []);

                expect($cacheId1)->not->toBe($cacheId2);
            });
        });
    });
});
