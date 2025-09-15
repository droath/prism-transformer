<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Contracts;

use Prism\Prism\Schema\ObjectSchema;
use Illuminate\Database\Eloquent\Model;
use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\ValueObjects\TransformerResult;

/**
 * Primary interface defining the core transformation contract.
 *
 * This interface provides a standardized way to transform content using
 * AI-powered transformations with comprehensive type safety, caching support,
 * and error handling. Implementations should extend BaseTransformer for
 * optimal functionality.
 *
 * @api
 *
 * @see \Droath\PrismTransformer\Abstract\BaseTransformer
 */
interface TransformerInterface
{
    /**
     * Get the unique name identifier for this transformer.
     *
     * Used for caching, logging, and debugging purposes. Should return
     * a unique string that identifies this specific transformer instance.
     * By default, this is typically the fully-qualified class name.
     *
     * @return string The transformer's unique identifier
     *
     * @example
     * ```php
     * // Typical implementation in BaseTransformer
     * public function getName(): string
     * {
     *     return static::class; // e.g., "App\Transformers\ArticleSummarizer"
     * }
     * ```
     */
    public function getName(): string;

    /**
     * Get the transformation prompt that instructs the LLM.
     *
     * This method should return the prompt that will be sent to the LLM
     * to instruct it on how to transform the input content. The prompt
     * should be specific, clear, and include any necessary context or
     * formatting instructions.
     *
     * @return string The transformation prompt for the LLM
     *
     * @example
     * ```php
     * public function prompt(): string
     * {
     *     return 'Summarize the following article in 2-3 sentences, '
     *          . 'focusing on the main points and key takeaways:';
     * }
     * ```
     */
    public function prompt(): string;

    /**
     * Get the AI provider to use for this transformation.
     *
     * Returns the Provider enum value that determines which LLM service
     * to use for this transformation. Different providers excel at different
     * types of tasks, so this allows per-transformer optimization.
     *
     * @return Provider The AI provider enum (OPENAI, ANTHROPIC, GROQ, etc.)
     *
     * @see \Droath\PrismTransformer\Enums\Provider
     *
     * @example
     * ```php
     * public function provider(): Provider
     * {
     *     // Use Anthropic for analysis tasks, OpenAI for generation
     *     return Provider::ANTHROPIC;
     * }
     * ```
     */
    public function provider(): Provider;

    /**
     * Get the specific model to use for transformation.
     *
     * Returns the model name/ID for the selected provider. This should
     * correspond to available models for the chosen provider. Model
     * selection affects performance, cost, and output quality.
     *
     * @return string The model identifier for the selected provider
     *
     * @example OpenAI models:
     * - 'gpt-4o-mini' (fast, cost-effective)
     * - 'gpt-4o' (high quality, slower)
     * @example Anthropic models:
     * - 'claude-3-5-haiku-20241022' (fast)
     * - 'claude-3-5-sonnet-20241022' (balanced)
     * @example
     * ```php
     * public function model(): string
     * {
     *     return 'gpt-4o-mini'; // Fast and cost-effective for summaries
     * }
     * ```
     */
    public function model(): string;

    /**
     * Execute the complete transformation pipeline.
     *
     * This method orchestrates the entire transformation process including:
     * 1. Cache lookup (if enabled)
     * 2. Pre-transformation hooks
     * 3. LLM transformation via Prism PHP
     * 4. Result processing and validation
     * 5. Cache storage
     * 6. Post-transformation hooks
     *
     * @param string $content The raw content to transform
     *
     * @return TransformerResult The transformation result containing:
     *                          - Transformed content
     *                          - Success/failure status
     *                          - Error messages (if any)
     *                          - Transformation metadata
     *
     * @throws \Droath\PrismTransformer\Exceptions\TransformerException
     *         When transformation fails due to LLM errors
     * @throws \Droath\PrismTransformer\Exceptions\ValidationException
     *         When input content fails validation
     * @throws \Droath\PrismTransformer\Exceptions\InvalidInputException
     *         When input content is malformed or empty
     *
     * @example
     * ```php
     * $content = "Long article text here...";
     * $result = $transformer->execute($content);
     *
     * if ($result->isSuccessful()) {
     *     echo $result->getContent();
     *     $metadata = $result->getMetadata();
     * } else {
     *     Log::error("Transformation failed: " . $result->getError());
     * }
     * ```
     */
    public function execute(string $content): TransformerResult;

    /**
     * Define the expected output format for structured transformations.
     *
     * This method allows transformers to specify a structured schema for
     * their output, enabling type-safe transformations with validation.
     * Return null for unstructured text output, or provide an ObjectSchema
     * for structured data output.
     *
     * @param ObjectSchema|Model $format Optional format specification
     *                                   to override the default
     *
     * @return ObjectSchema|null The output schema definition, or null for
     *                          unstructured text output
     *
     * @see \Prism\Prism\Schema\ObjectSchema
     * @see \Prism\Prism\Schema\StringSchema
     * @see \Prism\Prism\Schema\ArraySchema
     *
     * @example For structured contact extraction:
     * ```php
     * public function outputFormat(ObjectSchema|Model $format): ?ObjectSchema
     * {
     *     return ObjectSchema::create()
     *         ->properties([
     *             'name' => StringSchema::create()
     *                 ->description('Full contact name'),
     *             'email' => StringSchema::create()
     *                 ->format('email')
     *                 ->description('Email address'),
     *             'phone' => StringSchema::create()
     *                 ->description('Phone number'),
     *         ])
     *         ->required(['name']);
     * }
     */
    public function outputFormat(ObjectSchema|Model $format): ?ObjectSchema;
}
