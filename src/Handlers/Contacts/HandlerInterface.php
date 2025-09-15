<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Handles\Contacts;

interface HandlerInterface
{
    /**
     * The handler that returns a string.
     */
    public function handler(): ?string;
}
