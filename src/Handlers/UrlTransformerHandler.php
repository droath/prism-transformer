<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Handlers;

use Droath\PrismTransformer\ContentFetchers\BasicHttpFetcher;
use Droath\PrismTransformer\Contracts\ContentFetcherInterface;
use Droath\PrismTransformer\Handlers\Contracts\HandlerInterface;

class UrlTransformerHandler implements HandlerInterface
{
    public function __construct(
        protected string $url,
        protected ?ContentFetcherInterface $fetcher = null
    ) {
        $this->fetcher = $fetcher ?? resolve(BasicHttpFetcher::class);
    }

    /**
     * {@inheritDoc}
     */
    public function handle(): ?string
    {
        return $this->url;
    }
}
