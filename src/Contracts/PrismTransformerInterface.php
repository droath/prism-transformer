<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Contracts;

use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Illuminate\Foundation\Bus\PendingDispatch;

/**
 * Interface for the main PrismTransformer class.
 *
 * This interface defines the fluent API for setting up and executing
 * AI-powered content transformations. It supports text content, URL-based
 * content fetching, image processing, and document processing with
 * customizable transformers.
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
     * Set an image as the content source to be transformed.
     *
     * This method accepts image content through various input types (local files,
     * URLs, base64 data, etc.) and processes it using the ImageTransformerHandler.
     * The processed image will be converted to base64 format for consistent handling
     * in the transformation pipeline.
     *
     * @param string $path The image path or content, interpreted based on inputType option
     * @param array $options Configuration options for image processing:
     *   - inputType (string): How to interpret the $path parameter. Options:
     *     * 'localPath' (default): Local file system path
     *     * 'url': HTTP/HTTPS URL to fetch image from
     *     * 'base64': Base64-encoded image data
     *     * 'storagePath': Laravel storage disk path (requires 'disk' option)
     *     * 'rawContent': Raw binary image data (requires 'mimeType' option)
     *     * 'fileId': External file identifier for cloud storage
     *   - mimeType (string): MIME type for rawContent inputType (e.g., 'image/jpeg')
     *   - disk (string): Laravel storage disk name for storagePath inputType
     *
     * @return static Returns self for method chaining
     *
     * @throws \InvalidArgumentException When inputType is unsupported or required options are missing
     *
     * @example Basic local file:
     * ```php
     * $transformer->image('/path/to/image.jpg')
     *     ->using($imageAnalyzer)
     *     ->transform();
     * ```
     * @example URL-based image:
     * ```php
     * $transformer->image('https://example.com/image.jpg', ['inputType' => 'url'])
     *     ->using($imageProcessor)
     *     ->transform();
     * ```
     * @example Base64 image data:
     * ```php
     * $transformer->image($base64Data, ['inputType' => 'base64'])
     *     ->using($classifier)
     *     ->transform();
     * ```
     * @example Laravel storage disk:
     * ```php
     * $transformer->image('images/photo.png', [
     *     'inputType' => 'storagePath',
     *     'disk' => 's3'
     * ])->transform();
     * ```
     */
    public function image(string $path, array $options = []): static;

    /**
     * Set a document as the content source to be transformed.
     *
     * This method accepts document content through various input types (local files,
     * URLs, text content, binary data, etc.) and processes it using the DocumentTransformerHandler.
     * The processed document will be converted to base64 format for consistent handling
     * in the transformation pipeline.
     *
     * @param string $path The document path or content, interpreted based on inputType option
     * @param array $options Configuration options for document processing:
     *   - inputType (string): How to interpret the $path parameter. Options:
     *     * 'localPath' (default): Local file system path
     *     * 'url': HTTP/HTTPS URL to fetch document from
     *     * 'text': Plain text content (no file processing)
     *     * 'base64': Base64-encoded document data
     *     * 'storagePath': Laravel storage disk path (requires 'disk' option)
     *     * 'rawContent': Raw binary document data (requires 'mimeType' option)
     *     * 'fileId': External file identifier for cloud storage
     *   - title (string): Optional document title for metadata
     *   - mimeType (string): MIME type for rawContent inputType (e.g., 'application/pdf')
     *   - disk (string): Laravel storage disk name for storagePath inputType
     *
     * @return static Returns self for method chaining
     *
     * @throws \InvalidArgumentException When inputType is unsupported or required options are missing
     *
     * @example Basic local document:
     * ```php
     * $transformer->document('/path/to/report.pdf')
     *     ->using($documentSummarizer)
     *     ->transform();
     * ```
     * @example URL-based document:
     * ```php
     * $transformer->document('https://example.com/doc.pdf', ['inputType' => 'url'])
     *     ->using($documentAnalyzer)
     *     ->transform();
     * ```
     * @example Plain text with title:
     * ```php
     * $transformer->document('Document content here', [
     *     'inputType' => 'text',
     *     'title' => 'My Document'
     * ])->transform();
     * ```
     * @example Laravel storage with title:
     * ```php
     * $transformer->document('reports/annual.pdf', [
     *     'inputType' => 'storagePath',
     *     'disk' => 'public',
     *     'title' => 'Annual Report 2024'
     * ])->transform();
     * ```
     */
    public function document(string $path, array $options = []): static;

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
     *     - Closure: function (string $content, array $context = []): TransformerResult
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
