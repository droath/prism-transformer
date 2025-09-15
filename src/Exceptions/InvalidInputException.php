<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Exceptions;

use Exception;

/**
 * Exception thrown when invalid input is provided for transformation.
 *
 * This exception is thrown when input data fails validation or is not
 * in the expected format for a particular transformer.
 */
class InvalidInputException extends TransformerException
{
    /**
     * Create a new invalid input exception.
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param Exception|null $previous The previous exception used for chaining
     * @param array<string, mixed> $context Additional context about the error
     */
    public function __construct(
        string $message = 'Invalid input provided for transformation',
        int $code = 0,
        ?Exception $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}
