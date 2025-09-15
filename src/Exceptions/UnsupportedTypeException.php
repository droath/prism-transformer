<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Exceptions;

use Exception;

/**
 * Exception thrown when a transformer doesn't support the given input type.
 *
 * This exception is thrown when the transformer's supports() method
 * returns false, indicating that the transformer cannot handle the
 * provided input type.
 */
class UnsupportedTypeException extends TransformerException
{
    protected mixed $input;

    protected array $supportedTypes;

    /**
     * Create a new unsupported type exception.
     *
     * @param string $message The exception message
     * @param mixed $input The input that is not supported
     * @param array<string> $supportedTypes List of supported types
     * @param array<string, mixed> $context Additional context about the error
     * @param int $code The exception code
     * @param Exception|null $previous The previous exception used for chaining
     */
    public function __construct(
        string $message,
        mixed $input = null,
        array $supportedTypes = [],
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous, array_merge($context, ['supported_types' => $supportedTypes]));

        $this->input = $input;
        $this->supportedTypes = $supportedTypes;
    }

    /**
     * Get the input that is not supported.
     */
    public function getInput(): mixed
    {
        return $this->input;
    }

    /**
     * Get the list of supported types.
     *
     * @return array<string>
     */
    public function getSupportedTypes(): array
    {
        return $this->supportedTypes;
    }
}
