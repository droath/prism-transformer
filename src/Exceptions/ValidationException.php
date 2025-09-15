<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Exceptions;

use Exception;

/**
 * Exception thrown when input validation fails during transformation.
 *
 * This exception is thrown when the transformer's validate() method
 * returns false, indicating that the input data does not meet the
 * requirements for transformation.
 */
class ValidationException extends TransformerException
{
    protected mixed $input;

    protected array $validationErrors;

    /**
     * Create a new validation exception.
     *
     * @param string $message The exception message
     * @param mixed $input The input that failed validation
     * @param array<string, mixed> $validationErrors Specific validation errors
     * @param array<string, mixed> $context Additional context about the error
     * @param int $code The exception code
     * @param Exception|null $previous The previous exception used for chaining
     */
    public function __construct(
        string $message,
        mixed $input = null,
        array $validationErrors = [],
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous, $context);

        $this->input = $input;
        $this->validationErrors = $validationErrors;
    }

    /**
     * Get the input that failed validation.
     */
    public function getInput(): mixed
    {
        return $this->input;
    }

    /**
     * Get the validation errors that occurred.
     *
     * @return array<string, mixed>
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}
