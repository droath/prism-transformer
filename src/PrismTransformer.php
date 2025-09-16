<?php

namespace Droath\PrismTransformer;

use Droath\PrismTransformer\Handlers\UrlTransformerHandler;
use Droath\PrismTransformer\Contracts\PrismTransformerInterface;
use Droath\PrismTransformer\Contracts\TransformerInterface;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\Contracts\ContentFetcherInterface;

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
     * {@inheritDoc}
     */
    public function transform(): ?TransformerResult
    {
        return $this->handlerTransformer();
    }

    /**
     * Handle the transformer output result.
     */
    protected function handlerTransformer(): ?TransformerResult
    {
        $handler = $this->resolveHandler();

        if ($handler === null) {
            throw new \InvalidArgumentException(
                'Invalid transformer handler provided.'
            );
        }

        if ($handler instanceof TransformerInterface) {
            return $handler->execute(
                $this->content
            );
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
