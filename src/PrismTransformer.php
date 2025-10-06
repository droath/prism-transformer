<?php

namespace Droath\PrismTransformer;

use Droath\PrismTransformer\Handlers\TextTransformerHandler;
use Droath\PrismTransformer\Jobs\TransformationJob;
use Droath\PrismTransformer\Handlers\UrlTransformerHandler;
use Droath\PrismTransformer\Handlers\ImageTransformerHandler;
use Droath\PrismTransformer\Handlers\DocumentTransformerHandler;
use Droath\PrismTransformer\Contracts\PrismTransformerInterface;
use Droath\PrismTransformer\Contracts\TransformerInterface;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\ValueObjects\QueueableMedia;
use Droath\PrismTransformer\Contracts\ContentFetcherInterface;
use Droath\PrismTransformer\Services\RateLimitService;
use Illuminate\Foundation\Bus\PendingDispatch;
use Prism\Prism\ValueObjects\Media\Media;

/**
 * The main PrismTransformer class providing fluent interface for AI-powered
 * content transformation.
 *
 * This class serves as the primary entry point for the PrismTransformer
 * package, offering a fluent interface for setting up and executing AI-powered
 * content transformations. It supports text content, URL-based content
 * fetching, image processing, and document processing with customizable
 * transformers and asynchronous processing capabilities.
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
 * @example Image-based transformation:
 * ```php
 * $result = (new PrismTransformer())
 *     ->image('/path/to/image.jpg')
 *     ->using(new ImageAnalyzer())
 *     ->transform();
 * ```
 * @example Document-based transformation:
 * ```php
 * $result = (new PrismTransformer())
 *     ->document('/path/to/document.pdf')
 *     ->using(new DocumentSummarizer())
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
     * Options for asynchronous queue processing.
     *
     * Supported options:
     *   - delay (int): Number of seconds to delay the job execution (default: 0)
     */
    protected array $asyncOptions = [];

    /**
     * The input source for the transformation.
     */
    protected ?string $input = null;

    /**
     * The content to be transformed.
     *
     * This can be set directly via text() or populated by fetching from a URL
     * via the url() method. For media transformations (image/document), this
     * contains a Media object. Contains the raw content that will be passed to
     * the configured transformer.
     */
    protected string|Media|null $content = null;

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
     *   - A Closure that accepts string content and optional context array, returns TransformerResult
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
        $this->content = (new TextTransformerHandler($content))->handle();

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function url(
        string $url,
        ?ContentFetcherInterface $fetcher = null
    ): static {
        $this->input = $url;
        $this->content = (new UrlTransformerHandler($url, $fetcher))->handle();

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function image(string $path, array $options = []): static
    {
        $this->input = $path;
        $this->content = (new ImageTransformerHandler($path, $options))->handle();

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function document(string $path, array $options = []): static
    {
        $this->input = $path;
        $this->content = (new DocumentTransformerHandler($path, $options))->handle();

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
     * Set options for asynchronous queue processing.
     *
     * Configures how the transformation job should behave when queued.
     * This method allows fine-tuning of queue behavior such as execution delays.
     *
     * @param array $options Configuration options:
     *   - delay (int): Number of seconds to delay job execution (default: 0)
     *
     * @return static Returns this instance for method chaining
     *
     * @example Delay execution by 60 seconds:
     * ```php
     * $transformer->text($content)
     *     ->async()
     *     ->setAsyncOptions(['delay' => 60])
     *     ->using($transformer)
     *     ->transform();
     * ```
     * @example Process immediately (default):
     * ```php
     * $transformer->text($content)
     *     ->async()
     *     ->setAsyncOptions(['delay' => 0])
     *     ->using($transformer)
     *     ->transform();
     * ```
     */
    public function setAsyncOptions(array $options): static
    {
        $this->asyncOptions = $options;

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
    protected function handleAsyncTransformation(
        \Closure|TransformerInterface $handler
    ): TransformerResult|PendingDispatch|null {
        $job = TransformationJob::dispatch(
            $handler,
            $this->resolveContent(),
            $this->buildContext()
        );

        $delay = $this->asyncOptions['delay'] ?? 0;

        if ($delay > 0) {
            $job->delay($delay);
        }

        return $job;
    }

    /**
     * Handle synchronous transformation.
     */
    protected function handleSyncTransformation($handler): ?TransformerResult
    {
        $context = $this->buildContext();

        if (is_callable($handler)) {
            return $handler($this->content, $context);
        }

        if ($handler instanceof TransformerInterface) {
            return $handler->execute($this->content, $context);
        }

        return null;
    }

    /**
     * Define the context data to be passed to the transformer.
     *
     * @return null[]|string[]
     */
    protected function buildContext(): array
    {
        return [
            'input' => $this->input,
            ...$this->context,
        ];
    }

    /**
     * Resolve the content for async queue dispatch.
     *
     * Wraps Media objects in QueueableMedia for queue serialization.
     * Laravel queues use JSON which can't handle binary data, so we
     * convert Media objects to a queue-safe wrapper. Regular strings
     * pass through unchanged.
     */
    protected function resolveContent(): string|QueueableMedia|null
    {
        return $this->content instanceof Media
            ? QueueableMedia::fromMedia($this->content)
            : $this->content;
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

        if (
            is_string($this->transformerHandler)
            && class_exists($this->transformerHandler)
        ) {
            return resolve($this->transformerHandler);
        }

        return null;
    }
}
