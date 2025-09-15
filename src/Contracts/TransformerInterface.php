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
 * This interface provides a standardized way to transform data from one format
 * to another using AI-powered transformations with type safety.
 */
interface TransformerInterface
{
    /**
     * Get the unique name identifier for this transformer.
     *
     * @return string The transformer name
     */
    public function getName(): string;

    /**
     * Get the transformation prompt.
     *
     * This should return the prompt that will be sent to the LLM
     * to instruct it on how to transform the input content.
     */
    public function prompt(): string;

    /**
     * Get the AI provider to use it for transformation.
     *
     * This should return a Provider enum value (OpenAI, Anthropic, etc.)
     * that determines which LLM service to use.
     */
    public function provider(): Provider;

    /**
     * Get the specific model to use for transformation.
     *
     * This should return the model name/ID for the selected provider
     * (e.g., 'gpt-4o-mini', 'claude-3-sonnet', etc.).
     */
    public function model(): string;

    /**
     * Default cache ID implementation.
     *
     * Generates a unique cache key based on the transformer class,
     * prompt, provider, model.
     */
    public function cacheId(): string;

    /**
     * Handles the transformation pipeline.
     *
     * This method orchestrates the complete transformation flow:
     * 1. Pre-transformation hooks
     * 2. Prism PHP LLM transformation
     * 3. Post-transformation processing
     *
     * @param string $content The content to transform.
     */
    public function execute(string $content): TransformerResult;

    /**
     * Get the output format specification.
     *
     * This should return a Laravel model class name that defines
     * the expected structure of the transformation output.
     *
     * @param ObjectSchema|Model $format The output format specification
     */
    public function outputFormat(ObjectSchema|Model $format): ?ObjectSchema;
}
