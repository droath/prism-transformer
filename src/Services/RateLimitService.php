<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Services;

use Droath\PrismTransformer\Exceptions\RateLimitExceededException;
use Illuminate\Cache\RateLimiter;

/**
 * Service for managing rate limiting for transformation requests.
 *
 * This service provides rate-limiting functionality to prevent system overload
 * and ensure fair usage across users and applications.
 */
class RateLimitService
{
    public function __construct(
        protected RateLimiter $rateLimiter,
        protected ConfigurationService $configService
    ) {}

    /**
     * Check if the given key is rate-limited.
     *
     * @param string $key The rate limit key to check.
     *
     * @throws RateLimitExceededException If the rate limit is exceeded.
     */
    public function checkRateLimit(string $key): void
    {
        if (! $this->configService->isRateLimitingEnabled()) {
            return;
        }
        $rateLimitConfig = $this->configService->getRateLimitConfig();

        $maxAttempts = $rateLimitConfig['max_attempts'];
        $decayMinutes = $rateLimitConfig['decay_minutes'];

        if ($this->rateLimiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->rateLimiter->availableIn($key);

            throw new RateLimitExceededException($key, $maxAttempts, $retryAfter);
        }

        $this->rateLimiter->hit($key, $decayMinutes * 60);
    }

    /**
     * Check rate limits for a transformation request.
     *
     * This method applies global rate limiting.
     *
     * @throws RateLimitExceededException If the rate limit is exceeded.
     */
    public function checkTransformationRateLimit(): void
    {
        if (! $this->configService->isRateLimitingEnabled()) {
            return;
        }

        $this->checkRateLimit($this->getGlobalKey());
    }

    /**
     * Get the global rate limit key.
     *
     * @return string The global rate limit key.
     */
    public function getGlobalKey(): string
    {
        $rateLimitConfig = $this->configService->getRateLimitConfig();
        $prefix = $rateLimitConfig['key_prefix'];

        return "{$prefix}:global";
    }

    /**
     * Reset rate limit for a given key.
     *
     * @param string $key The rate limit key to reset.
     */
    public function resetRateLimit(string $key): void
    {
        $this->rateLimiter->clear($key);
    }

    /**
     * Reset rate limits for transformations.
     */
    public function resetTransformationRateLimit(): void
    {
        $this->resetRateLimit($this->getGlobalKey());
    }

    /**
     * Get remaining attempts for a given key.
     *
     * @param string $key The rate limit key.
     *
     * @return int The number of remaining attempts.
     */
    public function getRemainingAttempts(string $key): int
    {
        $rateLimitConfig = $this->configService->getRateLimitConfig();
        $maxAttempts = $rateLimitConfig['max_attempts'];

        return $this->rateLimiter->remaining($key, $maxAttempts);
    }

    /**
     * Get rate limit status for a given key.
     *
     * @param string $key The rate limit key.
     *
     * @return array<string, mixed> Rate limit status information.
     */
    public function getRateLimitStatus(string $key): array
    {
        $rateLimitConfig = $this->configService->getRateLimitConfig();
        $maxAttempts = $rateLimitConfig['max_attempts'];

        $attempts = $this->rateLimiter->attempts($key);
        $remaining = $this->rateLimiter->remaining($key, $maxAttempts);
        $retryAfter = $this->rateLimiter->availableIn($key);

        return [
            'key' => $key,
            'max_attempts' => $maxAttempts,
            'attempts' => $attempts,
            'remaining' => $remaining,
            'retry_after' => $retryAfter,
            'limited' => $this->rateLimiter->tooManyAttempts($key, $maxAttempts),
        ];
    }
}
