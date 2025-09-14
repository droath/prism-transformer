<?php

declare(strict_types=1);

namespace Droath\PrismTransformer;

/**
 * Represents the result of a transformation operation.
 *
 * This class encapsulates the status, data, metadata, and any errors
 * from a transformation operation performed by a transformer.
 */
class TransformationResult
{
    /**
     * Create a new transformation result.
     *
     * @param string $status The transformation status ('completed', 'failed', etc.)
     * @param mixed $data The transformed data (null if failed)
     * @param array<string, mixed> $metadata Additional metadata about the transformation
     * @param array<string> $errors Any errors that occurred during transformation
     */
    public function __construct(
        public readonly string $status,
        public readonly mixed $data = null,
        public readonly array $metadata = [],
        public readonly array $errors = []
    ) {}

    /**
     * Check if the transformation was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed' && empty($this->errors);
    }

    /**
     * Check if the transformation failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed' || ! empty($this->errors);
    }

    /**
     * Get the first error message if any errors occurred.
     */
    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Convert the result to an array.
     *
     * @return array<string, mixed>
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
     * Create a successful transformation result.
     *
     * @param mixed $data The transformed data
     * @param array<string, mixed> $metadata Additional metadata
     */
    public static function successful(mixed $data, array $metadata = []): self
    {
        return new self('completed', $data, $metadata);
    }

    /**
     * Create a failed transformation result.
     *
     * @param array<string> $errors The errors that occurred
     * @param array<string, mixed> $metadata Additional metadata
     */
    public static function failed(array $errors, array $metadata = []): self
    {
        return new self('failed', null, $metadata, $errors);
    }
}
