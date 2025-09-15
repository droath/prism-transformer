<?php

declare(strict_types=1);

use Droath\PrismTransformer\Enums\Provider;
use Illuminate\Support\Facades\Config;

describe('Provider Configuration Integration', function () {
    describe('defaultModel() method', function () {
        test('returns model from configuration when available', function () {
            Config::set('prism-transformer.providers.openai.default_model', 'gpt-4-custom');

            $model = Provider::OPENAI->defaultModel();

            expect($model)->toBe('gpt-4-custom');
        });

        test('returns fallback model when configuration is not available', function () {
            Config::set('prism-transformer.providers.openai.default_model', null);

            $model = Provider::OPENAI->defaultModel();

            expect($model)->toBe('gpt-4o-mini'); // Fallback value
        });

        test('works for all provider types', function () {
            $providerModels = [
                ['provider' => Provider::OPENAI, 'model' => 'custom-openai-model'],
                ['provider' => Provider::ANTHROPIC, 'model' => 'custom-anthropic-model'],
                ['provider' => Provider::GROQ, 'model' => 'custom-groq-model'],
                ['provider' => Provider::OLLAMA, 'model' => 'custom-ollama-model'],
                ['provider' => Provider::GEMINI, 'model' => 'custom-gemini-model'],
            ];

            foreach ($providerModels as $item) {
                $provider = $item['provider'];
                $customModel = $item['model'];

                Config::set("prism-transformer.providers.{$provider->value}.default_model", $customModel);

                expect($provider->defaultModel())->toBe($customModel);
            }
        });

        test('falls back to hardcoded values when config key does not exist', function () {
            // Clear any existing config
            Config::set('prism-transformer.providers', []);

            $expectedFallbacks = [
                ['provider' => Provider::XAI, 'model' => 'grok-beta'],
                ['provider' => Provider::GROQ, 'model' => 'llama-3.1-8b'],
                ['provider' => Provider::GEMINI, 'model' => 'gemini-2.0'],
                ['provider' => Provider::OLLAMA, 'model' => 'llama3.2:1b'],
                ['provider' => Provider::OPENAI, 'model' => 'gpt-4o-mini'],
                ['provider' => Provider::MISTRAL, 'model' => 'mistral-7b-instruct'],
                ['provider' => Provider::VOYAGEAI, 'model' => 'voyage-3-lite'],
                ['provider' => Provider::DEEPSEEK, 'model' => 'deepseek-chat'],
                ['provider' => Provider::ANTHROPIC, 'model' => 'claude-3-5-haiku-20241022'],
                ['provider' => Provider::OPENROUTER, 'model' => 'meta-llama/llama-3.2-1b-instruct:free'],
                ['provider' => Provider::ELEVENLABS, 'model' => 'eleven_turbo_v2_5'],
            ];

            foreach ($expectedFallbacks as $item) {
                expect($item['provider']->defaultModel())->toBe($item['model']);
            }
        });
    });

    describe('getConfig() method', function () {
        test('returns complete provider configuration', function () {
            $expectedConfig = [
                'default_model' => 'gpt-4-custom',
                'max_tokens' => 8192,
                'temperature' => 0.3,
                'custom_setting' => 'value',
            ];

            Config::set('prism-transformer.providers.openai', $expectedConfig);

            $config = Provider::OPENAI->getConfig();

            expect($config)->toBe($expectedConfig);
        });

        test('returns empty array when no configuration exists', function () {
            Config::set('prism-transformer.providers.openai', null);

            $config = Provider::OPENAI->getConfig();

            expect($config)->toBe([]);
        });

        test('works for different providers', function () {
            $configs = [
                ['provider' => Provider::ANTHROPIC, 'config' => ['model' => 'claude-custom', 'temp' => 0.5]],
                ['provider' => Provider::GROQ, 'config' => ['model' => 'llama-custom', 'speed' => 'fast']],
            ];

            foreach ($configs as $item) {
                $provider = $item['provider'];
                $providerConfig = $item['config'];

                Config::set("prism-transformer.providers.{$provider->value}", $providerConfig);

                expect($provider->getConfig())->toBe($providerConfig);
            }
        });
    });

    describe('getConfigValue() method', function () {
        test('returns specific configuration value', function () {
            Config::set('prism-transformer.providers.openai.max_tokens', 4096);

            $maxTokens = Provider::OPENAI->getConfigValue('max_tokens');

            expect($maxTokens)->toBe(4096);
        });

        test('returns default when configuration value does not exist', function () {
            $temperature = Provider::OPENAI->getConfigValue('temperature', 0.7);

            expect($temperature)->toBe(0.7);
        });

        test('returns null when no default is provided and value does not exist', function () {
            $value = Provider::OPENAI->getConfigValue('nonexistent_key');

            expect($value)->toBeNull();
        });

        test('can retrieve nested configuration values', function () {
            Config::set('prism-transformer.providers.elevenlabs.voice_settings.stability', 0.8);

            $stability = Provider::ELEVENLABS->getConfigValue('voice_settings.stability');

            expect($stability)->toBe(0.8);
        });

        test('works with different data types', function () {
            Config::set('prism-transformer.providers.openai', [
                'string_value' => 'test',
                'integer_value' => 42,
                'boolean_value' => true,
                'array_value' => ['a', 'b', 'c'],
            ]);

            expect(Provider::OPENAI->getConfigValue('string_value'))->toBe('test');
            expect(Provider::OPENAI->getConfigValue('integer_value'))->toBe(42);
            expect(Provider::OPENAI->getConfigValue('boolean_value'))->toBeTrue();
            expect(Provider::OPENAI->getConfigValue('array_value'))->toBe(['a', 'b', 'c']);
        });
    });

    describe('configuration consistency', function () {
        test('defaultModel() returns same value as getConfigValue for default_model', function () {
            Config::set('prism-transformer.providers.anthropic.default_model', 'claude-test');

            $modelFromDefaultMethod = Provider::ANTHROPIC->defaultModel();
            $modelFromConfigValue = Provider::ANTHROPIC->getConfigValue('default_model');

            expect($modelFromDefaultMethod)->toBe($modelFromConfigValue);
        });

        test('configuration methods work consistently across all providers', function () {
            foreach (Provider::cases() as $provider) {
                // Test that all methods work without throwing exceptions
                expect($provider->defaultModel())->toBeString();
                expect($provider->getConfig())->toBeArray();
                expect($provider->getConfigValue('default_model'))->toBeString();
            }
        });
    });

    describe('backward compatibility', function () {
        test('fallback models match original hardcoded values', function () {
            // Clear configuration to force fallback
            Config::set('prism-transformer.providers', []);

            // Test that fallback values match what was originally hardcoded
            expect(Provider::OPENAI->defaultModel())->toBe('gpt-4o-mini');
            expect(Provider::ANTHROPIC->defaultModel())->toBe('claude-3-5-haiku-20241022');
            expect(Provider::GROQ->defaultModel())->toBe('llama-3.1-8b');
            expect(Provider::OLLAMA->defaultModel())->toBe('llama3.2:1b');
        });

        test('enum still works when configuration is completely missing', function () {
            // Simulate missing configuration
            Config::set('prism-transformer', []);

            foreach (Provider::cases() as $provider) {
                expect($provider->defaultModel())->toBeString();
                expect($provider->defaultModel())->not->toBeEmpty();
            }
        });
    });
});
