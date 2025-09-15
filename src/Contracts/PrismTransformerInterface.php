<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Contracts;

use Droath\PrismTransformer\ValueObjects\TransformerResult;

interface PrismTransformerInterface
{
    /**
     * Define the transform text content.
     *
     * @return $this
     */
    public function text(string $content): static;

    /**
     * Define the transform URL content.
     *
     * @return $this
     */
    public function url(
        string $url,
        ?ContentFetcherInterface $fetcher = null
    ): static;

    /**
     * Set the transformer to run asynchronously.
     *
     * @return $this
     */
    public function async(): static;

    /**
     * Using the transformer closure or transformer instance.
     *
     * @return $this
     */
    public function using(
        \Closure|TransformerInterface $transformer
    ): static;

    /**
     * Transforms the input data using the transformer's logic'.
     *
     * @return TransformerResult|null
     *   The result of the transformation, or null if the transformation
     *   fails or no result is available.
     */
    public function transform(): ?TransformerResult;
}
