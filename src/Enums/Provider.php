<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Enums;

use Prism\Prism\Enums\Provider as PrismProvider;

/**
 * Enum representing supported AI providers for transformations.
 *
 * This enum provides type-safe provider selection with utility methods
 * for configuration and display purposes.
 */
enum Provider: string
{
    case XAI = 'xai';
    case GROQ = 'groq';
    case GEMINI = 'gemini';
    case OPENAI = 'openai';
    case OLLAMA = 'ollama';
    case MISTRAL = 'mistral';
    case VOYAGEAI = 'voyageai';
    case DEEPSEEK = 'deepseek';
    case ELEVENLABS = 'elevenlabs';
    case ANTHROPIC = 'anthropic';
    case OPENROUTER = 'openrouter';

    /**
     * Converts the object's state into a PrismProvider instance.
     *
     * @return PrismProvider|null
     *   The corresponding instance of PrismProvider, or null if no match.
     */
    public function toPrism(): ?PrismProvider
    {
        return match ($this) {
            self::XAI => PrismProvider::XAI,
            self::GROQ => PrismProvider::Groq,
            self::GEMINI => PrismProvider::Gemini,
            self::OLLAMA => PrismProvider::Ollama,
            self::OPENAI => PrismProvider::OpenAI,
            self::MISTRAL => PrismProvider::Mistral,
            self::VOYAGEAI => PrismProvider::VoyageAI,
            self::DEEPSEEK => PrismProvider::DeepSeek,
            self::ANTHROPIC => PrismProvider::Anthropic,
            self::OPENROUTER => PrismProvider::OpenRouter,
            self::ELEVENLABS => PrismProvider::ElevenLabs,
            default => null,
        };
    }

    /**
     * Returns the default model string based on the current instance.
     *
     * @return string The default model name corresponding to the current context.
     */
    public function defaultModel(): string
    {
        return match ($this) {
            self::XAI => 'grok-beta',
            self::GROQ => 'llama-3.1-8b',
            self::GEMINI => 'gemini-2.0',
            self::OLLAMA => 'llama3.2:1b',
            self::OPENAI => 'gpt-4o-mini',
            self::MISTRAL => 'mistral-7b-instruct',
            self::VOYAGEAI => 'voyage-3-lite',
            self::DEEPSEEK => 'deepseek-chat',
            self::ANTHROPIC => 'claude-3-5-haiku-20241022',
            self::OPENROUTER => 'meta-llama/llama-3.2-1b-instruct:free',
            self::ELEVENLABS => 'eleven_turbo_v2_5',
        };
    }
}
