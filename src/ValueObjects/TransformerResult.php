<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * Immutable value object representing the result of a transformation operation.
 *
 * This class encapsulates all information about a transformation operation,
 * including the status, transformed data, metadata about the transformation
 * process, and any errors that occurred. It provides a consistent interface
 * for handling transformation results across the entire package.
 *
 * The class is readonly (immutable) to ensure thread safety and prevent
 * accidental modification after creation. It implements Laravel's Arrayable
 * and Jsonable interfaces for easy serialization.
 *
 * @example Handling successful results:
 * ```php
 * $result = TransformerResult::successful("Transformed content");
 *
 * if ($result->isSuccessful()) {
 *     echo $result->getContent();
 *     $metadata = $result->getMetadata();
 * }
 * ```
 * @example Handling failed results:
 * ```php
 * $result = TransformerResult::failed(["API rate limit exceeded"]);
 *
 * if ($result->isFailed()) {
 *     foreach ($result->getErrors() as $error) {
 *         Log::error("Transformation error: $error");
 *     }
 * }
 * ```
 *
 * @immutable
 *
 * @api
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
     * Get all errors that occurred during transformation.
     *
     * @return string[] Array of error messages, empty if no errors occurred
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the first error message, if any.
     *
     * @return string|null The first error message, or null if no errors
     */
    public function getError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Get the transformed content.
     *
     * @return string|null The transformed content, or null if transformation failed
     */
    public function getContent(): ?string
    {
        return $this->data;
    }

    /**
     * Get the transformation metadata.
     *
     * @return TransformerMetadata|null Metadata about the transformation process
     */
    public function getMetadata(): ?TransformerMetadata
    {
        return $this->metadata;
    }

    /**
     * Get the transformation status.
     *
     * @return string One of the STATUS_* constants
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Check if the transformation was successful.
     *
     * A transformation is considered successful if it has completed status
     * and no errors occurred during the process.
     *
     * @return bool True if transformation succeeded, false otherwise
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_COMPLETED && empty($this->errors);
    }

    /**
     * Check if the transformation failed.
     *
     * A transformation is considered failed if it has failed status
     * or if any errors occurred during the process.
     *
     * @return bool True if transformation failed, false otherwise
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED || ! empty($this->errors);
    }

    /**
     * Check if the transformation is still pending.
     *
     * @return bool True if transformation is pending, false otherwise
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the transformation is currently in progress.
     *
     * @return bool True if transformation is in progress, false otherwise
     */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Legacy alias for getContent().
     *
     *
     * @deprecated Use getContent() instead
     */
    public function getTransformedContent(): ?string
    {
        return $this->getContent();
    }

    /**
     * Legacy alias for getErrors().
     *
     * @return string[]
     *
     * @deprecated Use getErrors() instead
     */
    public function errors(): array
    {
        return $this->getErrors();
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
