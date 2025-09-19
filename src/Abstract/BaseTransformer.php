<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Abstract;

use Prism\Prism\Prism;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Cache\CacheManager;
use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Services\ModelSchemaService;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\ValueObjects\TransformerMetadata;
use Droath\PrismTransformer\Contracts\TransformerInterface;

/**
 * Foundation abstract class providing core LLM transformation functionality.
 *
 * This class implements the template method pattern for AI-powered
 * transformations using Prism PHP integration. It provides comprehensive
 * functionality including:
 *
 * - Intelligent two-layer caching (content fetching and transformation results)
 * - Configuration-driven provider and model selection
 * - Pre- / post-transformation hooks for extensibility
 * - Error handling with detailed context
 * - Performance optimization and resource management
 *
 * Concrete transformers should extend this class and implement the abstract
 * methods to define their specific transformation behavior. The class handles
 * all the infrastructure concerns, allowing implementations to focus on the
 * transformation logic.
 *
 * @example Basic transformer implementation:
 * ```php
 * class ArticleSummarizer extends BaseTransformer
 * {
 *     public function prompt(): string
 *     {
 *         return 'Summarize the following article in 2-3 sentences:';
 *     }
 * }
 * ```
 *
 * @api
 */
abstract class BaseTransformer implements TransformerInterface
{
    /**
     * Create a new BaseTransformer instance.
     *
     * @param CacheManager $cache
     *   Cache manager for handling both content and transformation caching
     * @param ConfigurationService $configuration
     *   Service providing access to package configuration including provider
     *   settings and defaults
     * @param ModelSchemaService $modelSchemaService
     *   Service for converting Eloquent models to Prism schemas
     */
    public function __construct(
        protected CacheManager $cache,
        protected ConfigurationService $configuration,
        protected ModelSchemaService $modelSchemaService
    ) {}

    /**
     * {@inheritDoc}
     */
    public function execute(string $content): TransformerResult
    {
        if (
            ($result = $this->getCache())
            && $result->isSuccessful()
        ) {
            return $result;
        }
        $this->beforeTransform($content);

        $result = $this->performTransformation($content);

        if ($result->isSuccessful()) {
            $this->setCache($result);
        }

        $this->afterTransform($result);

        return $result;
    }

    /**
     * Get the unique name identifier for this transformer.
     *
     * Used for caching, logging, and debugging purposes. Returns
     * the fully qualified class name as a unique identifier.
     *
     * @return string The transformer's unique identifier
     */
    protected function getName(): string
    {
        return static::class;
    }

    /**
     * Get the AI provider to use it for this transformation.
     *
     * Returns the default provider from configuration. Can be overridden
     * by concrete transformers for provider-specific optimizations.
     *
     * @return Provider The AI provider enum
     */
    protected function provider(): Provider
    {
        return $this->configuration->getDefaultProvider();
    }

    /**
     * Get the specific model to use for transformation.
     *
     * Returns the default model for the selected provider. Can be overridden
     * by concrete transformers for model-specific optimizations.
     *
     * @return string The model identifier for the selected provider
     */
    protected function model(): string
    {
        return $this->provider()->defaultModel();
    }

    /**
     * Define the expected output format for structured transformations.
     *
     * This method allows transformers to specify a structured schema for
     * their output. Return null for unstructured text output, or provide
     * an ObjectSchema for structured data output.
     *
     * @return \Prism\Prism\Schema\ObjectSchema|\Illuminate\Database\Eloquent\Model|null
     *   The output schema definition, or null for text output
     */
    protected function outputFormat(): null|ObjectSchema|Model
    {
        return null;
    }

    /**
     * Define the tools available to the LLM for function calling.
     *
     * Override this method in concrete transformers to provide tools that the
     * LLM can use during the transformation process. Tools enable the model to
     * call functions and receive structured responses, expanding its
     * capabilities beyond simple text generation.
     *
     * Each tool should be defined as an array with the following structure:
     * - name: The function name (required)
     * - description: Human-readable description of what the tool does (required)
     * - parameters: JSON schema defining the expected parameters (optional)
     *
     * @return array An array of tool definitions for function calling
     *
     * @example Basic tool definition:
     * ```php
     * protected function tools(): array
     * {
     *     return [
     *         [
     *             'name' => 'get_weather',
     *             'description' => 'Get current weather information for a
     *     location',
     *             'parameters' => [
     *                 'type' => 'object',
     *                 'properties' => [
     *                     'location' => [
     *                         'type' => 'string',
     *                         'description' => 'The city name'
     *                     ]
     *                 ],
     *                 'required' => ['location']
     *             ]
     *         ]
     *     ];
     * }
     * ```
     */
    protected function tools(): array
    {
        return [];
    }

    /**
     * Define the temperature setting for controlling LLM response randomness.
     *
     * Override this method in concrete transformers to set a specific
     * temperature value that controls the randomness and creativity of the
     * model's responses. Temperature affects how the model selects tokens
     * during generation, with lower values producing more focused and
     * deterministic outputs, while higher values encourage more creative and
     * varied responses.
     *
     * Returning null will use the provider's default temperature setting,
     * allowing for provider-specific optimizations and configurations.
     *
     * @return float|null
     *   The temperature value (typically 0.0-2.0) or null for provider default
     */
    protected function temperature(): ?float
    {
        return null;
    }

    /**
     * Define the topP setting for nucleus sampling in LLM responses.
     *
     * Override this method in concrete transformers to set a specific topP
     * value that controls nucleus sampling during token generation. TopP (also
     * known as nucleus sampling) considers only the tokens whose cumulative
     * probability mass is within the specified threshold, filtering out
     * unlikely tokens to improve response quality.
     *
     * Lower values (e.g., 0.1) result in more focused and deterministic
     * outputs
     * by considering only the most likely tokens, while higher values (e.g.,
     * 0.9) allow for more diverse and creative responses by including a
     * broader range of possible tokens.
     *
     * Returning null will use the provider's default topP setting, if
     * available.
     *
     * @return float|null
     *   The topP value (typically 0.0-1.0) or null for provider default
     */
    protected function topP(): ?float
    {
        return null;
    }

    /**
     * Define the system prompt message for LLM context setting.
     *
     * Override this method in concrete transformers to provide system-level
     * instructions that guide the model's behavior and establish context for
     * the transformation. System messages are typically used to set the role,
     * tone, constraints, or behavioral guidelines for the AI assistant.
     *
     * Returning null (default) means no system message will be included,
     * leaving the model to operate with its default behavior.
     *
     * @return string|null
     *   The system prompt message or null for no system message
     *
     * @example Setting model behavior:
     * ```php
     * protected function systemPrompt(): ?string
     * {
     *     return 'You are a helpful assistant specialized in content summarization.
     *             Be concise and focus on key points.';
     * }
     * ```
     */
    protected function systemPrompt(): ?string
    {
        return null;
    }

    /**
     * Define model schema configuration for structured output generation.
     *
     * Override this method in concrete transformers to explicitly control
     * which fields should be included, their types, and whether they are
     * required when generating schemas from models. This provides fine-grained
     * control over the transformation output structure.
     *
     * If this method returns an empty array (default), the system will
     * fall back to automatic detection using model-fillable attributes
     * and cast types.
     *
     * @return array<string, array{required?: bool, type?: string}>
     *   Configuration array mapping field names to their configuration:
     *   - required: Whether the field is required (defaults to false if not set)
     *   - type: The JSON schema type for the field (defaults to model cast or 'string')
     *
     * @example Explicit schema configuration:
     * ```php
     * protected function getModelSchemaConfig(): array
     * {
     *     return [
     *         'title' => [
     *             'required' => true,
     *             'type' => 'string'
     *         ],
     *         'content' => [
     *             'required' => true,
     *             'type' => 'string'
     *         ],
     *         'summary' => [
     *             'required' => false,
     *             'type' => 'string'
     *         ],
     *         'word_count' => [
     *             'type' => 'integer'
     *             // required defaults to false
     *         ],
     *         'is_published' => [
     *             'type' => 'boolean'
     *             // required defaults to false
     *         ]
     *     ];
     * }
     * ```
     */
    protected function getModelSchemaConfig(): array
    {
        return [];
    }

    /**
     * Prism PHP integration for LLM transformation.
     *
     * This method uses Prism::structured() to perform the actual
     * AI transformation using the configured provider and model.
     *
     * @param string $content
     *   The content to transform.
     *
     * @return TransformerResult
     *   The transformation results with data and metadata.
     */
    protected function performTransformation(string $content): TransformerResult
    {
        try {
            $response = $this->makeRequest($content);

            return TransformerResult::successful(
                $this->extractResponseData($response),
                TransformerMetadata::make(
                    $this->model(),
                    $this->provider(),
                    static::class
                )
            );
        } catch (\Throwable $e) {
            return TransformerResult::failed(
                [$e->getMessage()],
                TransformerMetadata::make(
                    $this->model(),
                    $this->provider(),
                    static::class
                )
            );
        }
    }

    /**
     * Extract the response data from the response object.
     *
     * @throws \JsonException
     */
    protected function extractResponseData(
        TextResponse|StructuredResponse $response
    ): string {
        if (
            $response instanceof StructuredResponse
            && $response->structured !== null
        ) {
            return json_encode($response->structured, JSON_THROW_ON_ERROR);
        }

        return $response->text;
    }

    /**
     * Make the request to the LLM provider.
     */
    protected function makeRequest(string $content): TextResponse|StructuredResponse
    {
        $provider = $this->provider()->toPrism();
        $outputFormat = $this->outputFormat();

        $resource = $outputFormat !== null
            ? Prism::structured()
            : Prism::text();

        $resource
            ->using($provider, $this->model())
            ->withMessages(
                $this->structureMessages($content)
            );

        if ($topP = $this->topP()) {
            $resource->usingTopP($topP);
        }

        if ($temperature = $this->resolveTemperature()) {
            $resource->usingTemperature($temperature);
        }

        if ($outputFormat = $this->resolveOutputFormat()) {
            $resource->withSchema(
                $outputFormat
            );
        }

        return $outputFormat !== null
            ? $resource->asStructured()
            : $resource->asText();
    }

    /**
     * Configure the resource with messages for the LLM transformation.
     *
     * This method handles the message structure setup for Prism resources,
     * providing the prompt and content as separate user messages. If a system
     * prompt is defined, it will be included as the first message.
     *
     * Concrete transformers can override this method to customize message handling,
     * such as adding system messages, conversation history, or different
     * message types.
     *
     * @param string $content
     *   The content to be transformed
     */
    protected function structureMessages(string $content): array
    {
        $messages = [];

        if ($systemPrompt = $this->systemPrompt()) {
            $messages[] = new SystemMessage($systemPrompt);
        }

        $messages[] = new UserMessage($this->prompt());
        $messages[] = new UserMessage($content);

        return $messages;
    }

    /**
     * Resolve the transformer temperature value.
     */
    protected function resolveTemperature(): ?float
    {
        return $this->temperature()
            ?? $this->provider()->getConfigValue('temperature');
    }

    /**
     * Resolve the transformer output format.
     *
     * Converts various output format types (null, ObjectSchema, Model) into
     * a standardized ObjectSchema instance using the ModelSchemaService for
     * Model conversions.
     *
     * Uses the transformer's schema configuration when working with Model
     * instances.
     */
    protected function resolveOutputFormat(): ?ObjectSchema
    {
        $outputFormat = $this->outputFormat();

        return match (true) {
            $outputFormat instanceof ObjectSchema => $outputFormat,
            $outputFormat instanceof Model => $this->modelSchemaService->convertModelToSchema(
                $outputFormat,
                $this->getModelSchemaConfig()
            ),
            default => null,
        };
    }

    /**
     * Hook called before the transformation begins.
     *
     * Override in concrete classes or traits for custom pre-processing
     * like input validation, content sanitization, etc.
     */
    protected function beforeTransform(string $content): void {}

    /**
     * Hook called after transformation completes.
     *
     * Override in concrete classes or traits for custom post-processing
     * like result validation, caching, logging, etc.
     */
    protected function afterTransform(TransformerResult $result): void {}

    /**
     * The transformer cache ID.
     */
    protected function cacheId(): string
    {
        return hash('sha256', serialize(array_filter([
            static::class,
            $this->prompt(),
            $this->systemPrompt(),
            $this->provider()->value,
            $this->topP(),
            $this->model(),
            $this->tools(),
            $this->temperature(),
        ])));
    }

    /**
     * Get the transformer cache result.
     */
    protected function getCache(): ?TransformerResult
    {
        if (! $this->configuration->isCacheEnabled()) {
            return null;
        }

        try {
            return $this->getCacheStore()->get(
                $this->buildCacheKey(),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Set the transformer cache result.
     */
    protected function setCache($result): bool
    {
        if (! $this->configuration->isCacheEnabled()) {
            return false;
        }
        $ttl = $this->configuration->getTransformerDataCacheTtl();

        try {
            return $this->getCacheStore()->put(
                $this->buildCacheKey(),
                $result,
                $ttl
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get the cache store instance.
     *
     * @return \Illuminate\Contracts\Cache\Repository
     *   The cache store.
     */
    protected function getCacheStore(): Repository
    {
        $storeName = $this->configuration->getCacheStore();

        try {
            return $this->cache->store($storeName);
        } catch (\Throwable) {
            return Cache::store();
        }
    }

    /**
     * Build the cache key with a configured prefix.
     *
     * @return string The complete cache key.
     */
    protected function buildCacheKey(): string
    {
        return "{$this->configuration->getCachePrefix()}:{$this->cacheId()}";
    }
}
