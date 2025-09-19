<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Exceptions;

use Exception;

/**
 * Exception thrown when rate limit is exceeded for transformation requests.
 *
 * This exception is thrown when a user or the system exceeds the configured
 * rate limit for transformation requests, helping prevent system overload.
 */
class RateLimitExceededException extends Exception
{
    public function __construct(
        public readonly string $key,
        public readonly int $maxAttempts,
        public readonly int $retryAfter,
        string $message = '',
        int $code = 429,
        ?\Throwable $previous = null
    ) {
        if (empty($message)) {
            $message = "Rate limit exceeded for key '{$key}'. Maximum {$maxAttempts} attempts allowed. Try again in {$retryAfter} seconds.";
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the rate limit key that was exceeded.
     *
     * @return string The rate limit key.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get the maximum number of attempts allowed.
     *
     * @return int The maximum attempts.
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Get the number of seconds to wait before retrying.
     *
     * @return int The retry after seconds.
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
