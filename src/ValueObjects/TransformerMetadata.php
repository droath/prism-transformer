<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use Droath\PrismTransformer\Enums\Provider;

/**
 * Core transformer results metadata.
 */
readonly class TransformerMetadata implements Arrayable, Jsonable, JsonSerializable
{
    public string $timestamp;

    protected function __construct(
        public string $model,
        public Provider $provider,
        public ?string $transformerClass,
        public ?string $content,
    ) {
        $this->timestamp = now()->toISOString();
    }

    public static function make(
        string $model,
        Provider $provider,
        ?string $transformerClass = null,
        ?string $content = null
    ): self {
        return new self(
            $model,
            $provider,
            $transformerClass,
            $content,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            model: $data['model'] ?? '',
            provider: $data['provider'] ?? '',
            transformerClass: $data['transformerClass'] ?? null,
            content: $data['content'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'provider' => $this->provider,
            'timestamp' => $this->timestamp,
            'content' => $this->content,
        ];
    }

    public function toJson($options = JSON_THROW_ON_ERROR): string
    {
        return json_encode(
            $this->toArray(),
            $options
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
