<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Tests\Stubs;

use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Services\ModelSchemaService;
use Illuminate\Cache\CacheManager;

class SummarizeTransformer extends BaseTransformer
{
    public function __construct(
        CacheManager $cache,
        ConfigurationService $configuration,
        ModelSchemaService $modelSchemaService
    ) {
        parent::__construct($cache, $configuration, $modelSchemaService);
    }

    public function prompt(): string
    {
        return 'Summary the following text in 2-3 sentences';
    }

    public function provider(): Provider
    {
        return Provider::OPENAI;
    }
}
