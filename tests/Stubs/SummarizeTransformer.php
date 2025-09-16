<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Tests\Stubs;

use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\Abstract\BaseTransformer;

class SummarizeTransformer extends BaseTransformer
{
    public function prompt(): string
    {
        return 'Summary the following text in 2-3 sentences';
    }

    public function provider(): Provider
    {
        return Provider::OPENAI;
    }
}
