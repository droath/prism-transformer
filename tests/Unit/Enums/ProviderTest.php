<?php

declare(strict_types=1);

use Droath\PrismTransformer\Enums\Provider;
use Prism\Prism\Enums\Provider as PrismProvider;

describe('Provider Enum', function () {
    test('enum exists and is backed by string', function () {
        expect(Provider::OPENAI->value)->toBe('openai');
        expect(Provider::ANTHROPIC->value)->toBe('anthropic');
        expect(Provider::GROQ->value)->toBe('groq');
    });

    test('has all required provider cases', function () {
        $cases = Provider::cases();
        $values = array_map(fn ($case) => $case->value, $cases);

        expect($values)->toContain('xai');
        expect($values)->toContain('groq');
        expect($values)->toContain('gemini');
        expect($values)->toContain('openai');
        expect($values)->toContain('ollama');
        expect($values)->toContain('mistral');
        expect($values)->toContain('voyageai');
        expect($values)->toContain('deepseek');
        expect($values)->toContain('elevenlabs');
        expect($values)->toContain('anthropic');
        expect($values)->toContain('openrouter');
    });

    test('provider cases have correct string values', function () {
        expect(Provider::XAI->value)->toBe('xai');
        expect(Provider::GROQ->value)->toBe('groq');
        expect(Provider::GEMINI->value)->toBe('gemini');
        expect(Provider::OPENAI->value)->toBe('openai');
        expect(Provider::OLLAMA->value)->toBe('ollama');
        expect(Provider::MISTRAL->value)->toBe('mistral');
        expect(Provider::VOYAGEAI->value)->toBe('voyageai');
        expect(Provider::DEEPSEEK->value)->toBe('deepseek');
        expect(Provider::ELEVENLABS->value)->toBe('elevenlabs');
        expect(Provider::ANTHROPIC->value)->toBe('anthropic');
        expect(Provider::OPENROUTER->value)->toBe('openrouter');
    });
});

describe('Provider Enum Integration', function () {
    test('supports from for strict conversion', function () {
        $provider = Provider::from('openai');
        expect($provider)->toBe(Provider::OPENAI);
    });

    test('supports tryFrom for safe conversion', function () {
        $provider = Provider::tryFrom('openai');
        expect($provider)->toBe(Provider::OPENAI);

        $invalid = Provider::tryFrom('invalid');
        expect($invalid)->toBeNull();
    });

    test('can be serialized and deserialized', function () {
        $provider = Provider::ANTHROPIC;
        $serialized = serialize($provider);
        $unserialized = unserialize($serialized);

        expect($unserialized)->toBe(Provider::ANTHROPIC);
    });

    test('can be used in match expressions', function () {
        $provider = Provider::OPENAI;

        $result = match ($provider) {
            Provider::OPENAI => 'OpenAI selected',
            Provider::ANTHROPIC => 'Anthropic selected',
            default => 'Other provider'
        };

        expect($result)->toBe('OpenAI selected');
    });
});

describe('Provider Prism Integration Methods', function () {
    test('can convert to internal prism provider enum', function () {
        expect(Provider::OPENAI->toPrism())->toBe(PrismProvider::OpenAI);
        expect(Provider::ANTHROPIC->toPrism())->toBe(PrismProvider::Anthropic);
        expect(Provider::GROQ->toPrism())->toBe(PrismProvider::Groq);
        expect(Provider::GEMINI->toPrism())->toBe(PrismProvider::Gemini);
        expect(Provider::OLLAMA->toPrism())->toBe(PrismProvider::Ollama);
        expect(Provider::MISTRAL->toPrism())->toBe(PrismProvider::Mistral);
        expect(Provider::VOYAGEAI->toPrism())->toBe(PrismProvider::VoyageAI);
        expect(Provider::DEEPSEEK->toPrism())->toBe(PrismProvider::DeepSeek);
        expect(Provider::OPENROUTER->toPrism())->toBe(PrismProvider::OpenRouter);
        expect(Provider::ELEVENLABS->toPrism())->toBe(PrismProvider::ElevenLabs);
        expect(Provider::XAI->toPrism())->toBe(PrismProvider::XAI);
    });

    test('returns default models for each provider', function () {
        expect(Provider::XAI->defaultModel())->toBe('grok-beta');
        expect(Provider::GROQ->defaultModel())->toBe('llama-3.1-8b');
        expect(Provider::GEMINI->defaultModel())->toBe('gemini-2.0');
        expect(Provider::OPENAI->defaultModel())->toBe('gpt-4o-mini');
        expect(Provider::OLLAMA->defaultModel())->toBe('llama3.2:1b');
        expect(Provider::MISTRAL->defaultModel())->toBe('mistral-7b-instruct');
        expect(Provider::VOYAGEAI->defaultModel())->toBe('voyage-3-lite');
        expect(Provider::DEEPSEEK->defaultModel())->toBe('deepseek-chat');
        expect(Provider::ELEVENLABS->defaultModel())->toBe('eleven_turbo_v2_5');
        expect(Provider::ANTHROPIC->defaultModel())->toBe('claude-3-5-haiku-20241022');
        expect(Provider::OPENROUTER->defaultModel())->toBe('meta-llama/llama-3.2-1b-instruct:free');
    });
});
