<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * Represents the result of a transformation operation.
 *
 * This class encapsulates the status, data, metadata, and any errors
 * from a transformation operation performed by a transformer.
 */
readonly class TransformerResult implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * Status constant for failed transformations.
     */
    public const string STATUS_FAILED = 'failed';

    /**
     * Status constant for pending transformations.
     */
    public const string STATUS_PENDING = 'pending';

    /**
     * Status constant for completed transformations.
     */
    public const string STATUS_COMPLETED = 'completed';

    /**
     * Status constant for in-progress transformations.
     */
    public const string STATUS_IN_PROGRESS = 'in_progress';

    /**
     * Create a new transformation result.
     *
     * @param string $status
     *   The transformation status (use STATUS_* constants)
     * @param ?string $data
     *   The transformed data (null if failed)
     * @param ?\Droath\PrismTransformer\ValueObjects\TransformerMetadata $metadata
     *   Additional metadata about the transformation
     * @param array<string> $errors
     *   Any errors that occurred during transformation
     */
    public function __construct(
        public string $status,
        public ?string $data = null,
        public ?TransformerMetadata $metadata = null,
        public array $errors = []
    ) {}

    /**
     * Create a successful transformation result.
     *
     * @param string $data
     *   The transformed data
     * @param \Droath\PrismTransformer\ValueObjects\TransformerMetadata|null $metadata
     *   Additional metadata
     */
    public static function successful(
        string $data,
        ?TransformerMetadata $metadata = null
    ): self {
        return new self(self::STATUS_COMPLETED, $data, $metadata);
    }

    /**
     * Create a failed transformation result.
     *
     * @param array<string> $errors
     *    The errors that occurred
     * @param \Droath\PrismTransformer\ValueObjects\TransformerMetadata|null $metadata
     *    Additional metadata
     */
    public static function failed(
        array $errors,
        ?TransformerMetadata $metadata = null
    ): self {
        return new self(
            self::STATUS_FAILED,
            null,
            $metadata,
            $errors
        );
    }

    /**
     * Create a new instance from array data.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'] ?? self::STATUS_PENDING,
            data: $data['data'] ?? null,
            metadata: $data['metadata'] ?? [],
            errors: $data['errors'] ?? []
        );
    }

    /**
     * Create a new instance from JSON string.
     *
     * @throws \JsonException If JSON is invalid
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new \InvalidArgumentException(
                'JSON must represent an object'
            );
        }

        return self::fromArray($data);
    }

    /**
     * @return string[]
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Check if the transformation was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_COMPLETED && empty($this->errors);
    }

    /**
     * Check if the transformation failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED || ! empty($this->errors);
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'data' => $this->data,
            'metadata' => $this->metadata,
            'errors' => $this->errors,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function toJson($options = JSON_THROW_ON_ERROR): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
