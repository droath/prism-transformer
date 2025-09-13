<?php

namespace Droath\PrismTransformer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Droath\PrismTransformer\PrismTransformer
 */
class PrismTransformer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Droath\PrismTransformer\PrismTransformer::class;
    }
}
