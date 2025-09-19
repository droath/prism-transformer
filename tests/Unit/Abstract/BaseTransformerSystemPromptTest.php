<?php

declare(strict_types=1);

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Services\ModelSchemaService;
use Illuminate\Cache\CacheManager;

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
});
