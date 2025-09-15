<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Handlers;

use Droath\PrismTransformer\ContentFetchers\BasicHttpFetcher;
use Droath\PrismTransformer\Contracts\ContentFetcherInterface;

class UrlTransformerHandler
{
    public function __construct(
        protected string $url,
        protected ?ContentFetcherInterface $fetcher = null
    ) {
        $this->fetcher = $fetcher ?? resolve(BasicHttpFetcher::class);
    }

    public function handle(): ?string
    {

        return $this->url;
    }
}
