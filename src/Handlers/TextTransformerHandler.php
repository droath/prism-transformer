<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Handlers;

use Droath\PrismTransformer\Handlers\Contracts\HandlerInterface;

class TextTransformerHandler implements HandlerInterface
{
    public function __construct(
        protected string $content
    ) {}

    /**
     * {@inheritDoc}
     */
    public function handle(): ?string
    {
        return $this->content;
    }
}
