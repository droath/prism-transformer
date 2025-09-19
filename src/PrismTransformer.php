<?php

namespace Droath\PrismTransformer;

use Illuminate\Support\Facades\Queue;
use Droath\PrismTransformer\Jobs\TransformationJob;
use Droath\PrismTransformer\Handlers\UrlTransformerHandler;
use Droath\PrismTransformer\Contracts\PrismTransformerInterface;
use Droath\PrismTransformer\Contracts\TransformerInterface;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\Contracts\ContentFetcherInterface;
use Droath\PrismTransformer\Services\RateLimitService;
use Illuminate\Foundation\Bus\PendingDispatch;

/**
 * Main PrismTransformer class providing fluent interface for AI-powered
 * content transformation.
 *
 * This class serves as the primary entry point for the PrismTransformer
 * package, offering a fluent interface for setting up and executing AI-powered
 * content transformations. It supports both text content and URL-based content
 * fetching with customizable transformers and asynchronous processing
 * capabilities.
 *
 * The class follows the Builder pattern, allowing method chaining for an
 * intuitive API:
 *
 * @example Basic usage:
 * ```php
 * $result = (new PrismTransformer())
 *     ->text('Content to transform')
 *     ->using($transformer)
 *     ->transform();
 * ```
 * @example URL-based transformation:
 * ```php
 * $result = (new PrismTransformer())
 *     ->url('https://example.com/article')
 *     ->using(new ArticleSummarizer())
 *     ->transform();
 * ```
 * @example Asynchronous processing:
 * ```php
 * $result = (new PrismTransformer())
 *     ->text($content)
 *     ->async()
 *     ->using($transformer)
 *     ->transform(); // Queues for background processing
 * ```
 *
 * @api
 *
 * @see \Droath\PrismTransformer\Facades\PrismTransformer For facade access
 */
class PrismTransformer implements PrismTransformerInterface
{
    /**
     * Whether to execute the transformation asynchronously.
     *
     * When true, the transformation will be queued for background processing
     * using Laravel's queue system instead of executing synchronously.
     */
    protected bool $async = false;

    /**
     * The content to be transformed.
     *
     * This can be set directly via text() or populated by fetching from a URL
     * via the url() method. Contains the raw content that will be passed to
     * the configured transformer.
     */
    protected ?string $content = null;

    /**
     * Context data to be preserved throughout the transformation lifecycle.
     *
     * This associative array stores context information such as user_id,
     * tenant_id, and other metadata that should be preserved during async
     * processing and made available in events and job handlers.
     */
    protected array $context = [];

    /**
     * The transformer handler to use for content processing.
     *
     * Can be either:
     *   - A Closure that accepts string content and returns TransformerResult
     *   - A transform classname that resolves to TransformerInterface
     *   - A TransformerInterface instance
     *   - null if no transformer has been configured yet
     */
    protected null|\Closure|string|TransformerInterface $transformerHandler = null;

    /**
     * Class constructor.
     */
    public function __construct(
        protected RateLimitService $rateLimitService
    ) {}

    /**
     * {@inheritDoc}
     */
    public function text(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function url(
        string $url,
        ?ContentFetcherInterface $fetcher = null
    ): static {
        $this->content = (new UrlTransformerHandler($url, $fetcher))->handle();

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function async(): static
    {
        $this->async = true;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function using(
        \Closure|string|TransformerInterface $transformerHandler
    ): static {
        $this->transformerHandler = $transformerHandler;

        return $this;
    }

    /**
     * Set context data to be preserved throughout the transformation lifecycle.
     *
     * Context data is particularly useful for async processing where you need
     * to preserve information like user ID, tenant ID, or other metadata that
     * should be available in events and job handlers.
     *
     * @param array $context Associative array of context data
     *
     * @return static Returns this instance for method chaining
     *
     * @example Basic usage:
     * ```php
     * $transformer->setContext(['user_id' => 123, 'tenant_id' => 'acme']);
     * ```
     */
    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function transform(): TransformerResult|PendingDispatch|null
    {
        return $this->handlerTransformer();
    }

    /**
     * Handle the transformer output result.
     *
     * @throws \Droath\PrismTransformer\Exceptions\RateLimitExceededException
     */
    protected function handlerTransformer(): TransformerResult|PendingDispatch|null
    {
        $this->rateLimitService->checkTransformationRateLimit();

        $handler = $this->resolveHandler();

        if ($handler === null) {
            throw new \InvalidArgumentException(
                'Invalid transformer handler provided.'
            );
        }

        if ($this->async) {
            return $this->handleAsyncTransformation(
                $handler
            );
        }

        return $this->handleSyncTransformation($handler);
    }

    /**
     * Handle asynchronous transformation by dispatching a job.
     */
    protected function handleAsyncTransformation($handler): TransformerResult|PendingDispatch|null
    {
        if ($handler instanceof TransformerInterface) {
            return TransformationJob::dispatch(
                $handler,
                $this->content ?? '',
                $this->context
            );
        }

        // For closures, execute them synchronously since TransformationJob requires TransformerInterface
        if (is_callable($handler)) {
            return $handler($this->content);
        }

        // Debug: This should not happen
        // var_dump('No handler match found, returning null');
        return null;
    }

    /**
     * Handle synchronous transformation.
     */
    protected function handleSyncTransformation($handler): ?TransformerResult
    {
        if ($handler instanceof TransformerInterface) {
            return $handler->execute($this->content);
        }

        if (is_callable($handler)) {
            return $handler($this->content);
        }

        return null;
    }

    /**
     * Resolve the transformer handler.
     */
    protected function resolveHandler(): \Closure|TransformerInterface|null
    {
        if (
            $this->transformerHandler instanceof \Closure
            || $this->transformerHandler instanceof TransformerInterface
        ) {
            return $this->transformerHandler;
        }

        if (is_string($this->transformerHandler) && class_exists($this->transformerHandler)) {
            return resolve($this->transformerHandler);
        }

        return null;
    }
}
