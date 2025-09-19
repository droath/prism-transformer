<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Handlers\Contracts;

interface HandlerInterface
{
    /**
     * The handle that returns a string.
     */
    public function handle(): ?string;
}
