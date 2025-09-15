<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Exceptions;

use Exception;

/**
 * Exception thrown when content cannot be fetched from a source.
 *
 * This exception is thrown when a ContentFetcher implementation fails
 * to retrieve content from the specified source.
 */
class FetchException extends TransformerException
{
    /**
     * Create a new fetch exception.
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param Exception|null $previous The previous exception used for chaining
     * @param array<string, mixed> $context Additional context about the error
     */
    public function __construct(
        string $message = 'Failed to fetch content from source',
        int $code = 0,
        ?Exception $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}
