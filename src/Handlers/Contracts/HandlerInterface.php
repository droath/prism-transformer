<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Handlers\Contracts;

use Prism\Prism\ValueObjects\Media\Media;

interface HandlerInterface
{
    /**
     * The handle that returns a string or Media object.
     */
    public function handle(): string|Media|null;
}
