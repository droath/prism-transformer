<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Contracts;

use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Illuminate\Foundation\Bus\PendingDispatch;

/**
 * Interface for the main PrismTransformer class.
 *
 * This interface defines the fluent API for setting up and executing
 * AI-powered content transformations. It supports both text content
 * and URL-based content fetching with customizable transformers.
 *
 * @api
 */
interface PrismTransformerInterface
{
    /**
     * Set the text content to be transformed.
     *
     * This method accepts raw text content that will be passed to the
     * configured transformer for processing.
     *
     * @param string $content The text content to transform
     *
     * @return static Returns self for method chaining
     *
     * @example
     * ```php
     * $transformer->text('Hello, world!')
     *     ->using($myTransformer)
     *     ->transform();
     * ```
     */
    public function text(string $content): static;

    /**
     * Set a URL as the content source to be transformed.
     *
     * This method will fetch content from the specified URL using either
     * the provided content fetcher or the default HTTP fetcher. The fetched
     * content will then be passed to the configured transformer.
     *
     * @param string $url The URL to fetch content from
     * @param ContentFetcherInterface|null $fetcher Optional custom content fetcher.
     *                                               If null, uses the default BasicHttpFetcher
     *
     * @return static Returns self for method chaining
     *
     * @throws \Droath\PrismTransformer\Exceptions\FetchException When URL fetching fails
     *
     * @example
     * ```php
     * $transformer->url('https://example.com/article')
     *     ->using($summarizer)
     *     ->transform();
     * ```
     */
    public function url(
        string $url,
        ?ContentFetcherInterface $fetcher = null
    ): static;

    /**
     * Configure the transformer to run asynchronously.
     *
     * When enabled, the transformation will be queued for background processing
     * using Laravel's queue system. The transform() method will return immediately
     * with a queued job reference.
     *
     * @return static Returns self for method chaining
     *
     * @example
     * ```php
     * $transformer->text($content)
     *     ->async()
     *     ->using($transformer)
     *     ->transform(); // Returns immediately, processes in background
     * ```
     */
    public function async(): static;

    /**
     * Set the transformer to use for content processing.
     *
     * Accepts either a closure function or a TransformerInterface implementation.
     * The provided transformer will receive the content and return a TransformerResult.
     *
     * @param \Closure|string|TransformerInterface $transformer
     *   The transformer to use.
     *     - Closure: function (string $content): TransformerResult
     *     - string: Custom transformer classname
     *     - TransformerInterface: Direct transformer instance
     *
     * @return static Returns self for method chaining
     *
     * @example Using a closure:
     * ```php
     * $transformer->using(function($content) {
     *     return TransformerResult::successful("Processed: $content");
     * });
     * ```
     * @example Using a transformer class:
     * ```php
     * $transformer->using(app(ArticleSummarizer::class));
     * ```
     */
    public function using(
        \Closure|string|TransformerInterface $transformer
    ): static;

    /**
     * Set context data to be preserved throughout the transformation lifecycle.
     *
     * Context data is particularly useful for async processing where you need
     * to preserve information like user ID, tenant ID, or other metadata that
     * should be available in events and job handlers.
     *
     * @param array $context Associative array of context data
     *
     * @return static Returns self for method chaining
     *
     * @example
     * ```php
     * $transformer->setContext(['user_id' => 123, 'tenant_id' => 'acme'])
     *     ->text($content)
     *     ->async()
     *     ->using($transformer)
     *     ->transform();
     * ```
     */
    public function setContext(array $context): static;

    /**
     * Execute the transformation with the configured settings.
     *
     * This method triggers the actual transformation process using the content
     * and transformer that have been configured via method chaining. It handles
     * both synchronous and asynchronous execution based on the async() setting.
     *
     * @return TransformerResult|PendingDispatch|null
     *  The transformation result containing the processed content for sync
     *  execution, or a PendingDispatch for async execution. Returns null if no
     *  transformer is configured.
     *
     * @throws \Droath\PrismTransformer\Exceptions\TransformerException When transformation fails
     * @throws \Droath\PrismTransformer\Exceptions\ValidationException When input validation fails
     * @throws \Droath\PrismTransformer\Exceptions\RateLimitExceededException When the rate limit is exceeded
     *
     * @example Synchronous transformation:
     * ```php
     * $result = $transformer->text($content)
     *     ->using($summarizer)
     *     ->transform();
     *
     * if ($result->isSuccessful()) {
     *     echo $result->getContent();
     * }
     * ```
     */
    public function transform(): TransformerResult|PendingDispatch|null;
}
