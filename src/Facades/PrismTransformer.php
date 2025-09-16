<?php

namespace Droath\PrismTransformer\Facades;

use Illuminate\Support\Facades\Facade;
use Droath\PrismTransformer\Contracts\TransformerInterface;
use Droath\PrismTransformer\Contracts\ContentFetcherInterface;
use Droath\PrismTransformer\ValueObjects\TransformerResult;

/**
 * Laravel Facade for convenient static access to PrismTransformer functionality.
 *
 * This facade provides a static interface to the PrismTransformer class,
 * allowing for clean, expressive syntax when working with AI-powered
 * content transformations in Laravel applications.
 *
 * @example Basic text transformation:
 * ```php
 * use Droath\PrismTransformer\Facades\PrismTransformer;
 *
 * $result = PrismTransformer::text('Content to transform')
 *     ->using($transformer)
 *     ->transform();
 * ```
 * @example URL content transformation:
 * ```php
 * $result = PrismTransformer::url('https://example.com/article')
 *     ->using(new ArticleSummarizer())
 *     ->transform();
 * ```
 *
 * @method static \Droath\PrismTransformer\PrismTransformer text(string $content)
 * @method static \Droath\PrismTransformer\PrismTransformer url(string $url, ContentFetcherInterface|null $fetcher = null)
 * @method static \Droath\PrismTransformer\PrismTransformer async()
 * @method static \Droath\PrismTransformer\PrismTransformer using(\Closure|TransformerInterface $transformer)
 * @method static TransformerResult|null transform()
 *
 * @see \Droath\PrismTransformer\PrismTransformer The underlying service class
 *
 * @api
 */
class PrismTransformer extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string The service container binding key
     */
    protected static function getFacadeAccessor(): string
    {
        return \Droath\PrismTransformer\PrismTransformer::class;
    }
}
