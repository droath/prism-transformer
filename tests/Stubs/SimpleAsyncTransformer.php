<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Tests\Stubs;

use Droath\PrismTransformer\Contracts\TransformerInterface;
use Droath\PrismTransformer\ValueObjects\TransformerResult;

class SimpleAsyncTransformer implements TransformerInterface
{
    public function prompt(): string
    {
        return 'Transform the content for testing purposes';
    }

    public function execute(string $content, array $context = []): TransformerResult
    {
        return TransformerResult::successful('simple async: '.$content);
    }
}
