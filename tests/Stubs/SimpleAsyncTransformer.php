<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Tests\Stubs;

use Droath\PrismTransformer\Contracts\TransformerInterface;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Prism\Prism\ValueObjects\Media\Media;

class SimpleAsyncTransformer implements TransformerInterface
{
    public function prompt(): string
    {
        return 'Transform the content for testing purposes';
    }

    public function execute(string|Media $content, array $context = []): TransformerResult
    {
        $contentStr = is_string($content) ? $content : 'media object';

        return TransformerResult::successful('simple async: '.$contentStr);
    }
}
