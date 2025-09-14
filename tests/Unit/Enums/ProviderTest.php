<?php

declare(strict_types=1);

use Droath\PrismTransformer\Enums\Provider;

describe('Provider Enum', function () {
    test('can convert to internal prism provider enum', function () {
        $provider = Provider::OPENAI;
        $prismProvider = $provider->toPrism();

        expect($provider)->toBe(Provider::OPENAI)
            ->and($prismProvider)->toBe(\Prism\Prism\Enums\Provider::OpenAI);
    });
});
