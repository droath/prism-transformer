<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Contracts;

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
}
