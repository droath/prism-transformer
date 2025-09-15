<?php

namespace Droath\PrismTransformer;

use Droath\PrismTransformer\Handlers\UrlTransformerHandler;
use Droath\PrismTransformer\Contracts\PrismTransformerInterface;
use Droath\PrismTransformer\Contracts\TransformerInterface;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\Contracts\ContentFetcherInterface;

class PrismTransformer implements PrismTransformerInterface
{
    protected bool $async = false;

    protected ?string $content = null;

    protected null|\Closure|TransformerInterface $transformerHandler = null;

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
        \Closure|TransformerInterface $transformerHandler
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
        $handler = $this->transformerHandler;

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
}
