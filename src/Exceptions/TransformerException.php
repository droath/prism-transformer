<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Exceptions;

use Exception;

/**
 * Base exception for all transformation-related errors.
 *
 * This exception provides a foundation for all transformation errors
 * and includes context information to aid in debugging.
 */
class TransformerException extends Exception
{
    /**
     * Additional context information about the transformation error.
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Create a new transformation exception.
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param Exception|null $previous The previous exception used for chaining
     * @param array<string, mixed> $context Additional context about the error
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get the context information for this exception.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
