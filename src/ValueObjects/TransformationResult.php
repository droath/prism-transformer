<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Represents the result of a transformation operation.
 *
 * This class encapsulates the status, data, metadata, and any errors
 * from a transformation operation performed by a transformer.
 */
readonly class TransformationResult implements Arrayable
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
     * @param string $status The transformation status (use STATUS_* constants)
     * @param mixed $data The transformed data (null if failed)
     * @param array<string, mixed> $metadata Additional metadata about the transformation
     * @param array<string> $errors Any errors that occurred during transformation
     */
    public function __construct(
        public string $status,
        public mixed $data = null,
        public array $metadata = [],
        public array $errors = []
    ) {}

    /**
     * Create a successful transformation result.
     *
     * @param mixed $data The transformed data
     * @param array<string, mixed> $metadata Additional metadata
     */
    public static function successful(mixed $data, array $metadata = []): self
    {
        return new self(self::STATUS_COMPLETED, $data, $metadata);
    }

    /**
     * Create a failed transformation result.
     *
     * @param array<string> $errors The errors that occurred
     * @param array<string, mixed> $metadata Additional metadata
     */
    public static function failed(array $errors, array $metadata = []): self
    {
        return new self(self::STATUS_FAILED, null, $metadata, $errors);
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
}
