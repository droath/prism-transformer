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
     * Returns the default model string based on configuration or fallback defaults.
     *
     * @return string The default model name from configuration or hardcoded fallback.
     */
    public function defaultModel(): string
    {
        $configKey = "prism-transformer.providers.{$this->value}.default_model";

        return config($configKey) ?? $this->getFallbackModel();
    }

    /**
     * Gets the complete provider configuration from the config.
     *
     * @return array<string, mixed> The provider configuration array.
     */
    public function getConfig(): array
    {
        $configKey = "prism-transformer.providers.{$this->value}";
        $config = config($configKey, []);

        return is_array($config) ? $config : [];
    }

    /**
     * Gets a specific configuration value for this provider.
     *
     * @param string $key The configuration key to retrieve.
     * @param mixed $default The default value if the key doesn't exist.
     *
     * @return mixed The configuration value or default.
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        $configKey = "prism-transformer.providers.{$this->value}.{$key}";

        return config($configKey, $default);
    }

    /**
     * Returns the hardcoded fallback model for this provider.
     *
     * @return string The fallback model name when configuration is not available.
     */
    private function getFallbackModel(): string
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
