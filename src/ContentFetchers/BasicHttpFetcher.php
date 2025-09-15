<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\ContentFetchers;

use Droath\PrismTransformer\Abstract\BaseContentFetcher;
use Droath\PrismTransformer\Exceptions\FetchException;
use Droath\PrismTransformer\Services\ConfigurationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Cache\CacheManager;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Basic HTTP content fetcher using Laravel's HTTP client.
 *
 * This class provides HTTP content fetching capabilities with proper error
 * handling, logging, and URL validation for the prism-transformer package.
 */
class BasicHttpFetcher extends BaseContentFetcher
{
    protected int $timeout;

    /**
     * Create a new BasicHttpFetcher instance.
     *
     * @param HttpFactory $httpFactory Laravel HTTP client factory
     * @param LoggerInterface $logger Logger for error and debug information
     * @param CacheManager|null $cache Cache manager for response caching
     * @param ConfigurationService|null $configuration Configuration service for cache settings
     * @param int|null $timeout Request timeout in seconds (defaults to configured value or 30)
     *
     * @throws InvalidArgumentException When timeout is not positive
     */
    public function __construct(
        protected HttpFactory $httpFactory,
        protected LoggerInterface $logger,
        ?CacheManager $cache = null,
        ?ConfigurationService $configuration = null,
        ?int $timeout = null
    ) {
        parent::__construct($cache, $configuration);

        $this->timeout = $timeout ?? $configuration?->getHttpTimeout() ?? 30;

        if ($this->timeout < 1) {
            throw new InvalidArgumentException('Timeout must be positive');
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function performFetch(string $url): string
    {
        // Sanitize and validate URL format
        $sanitizedUrl = $this->sanitizeUrl($url);

        if (! $this->isValidUrl($sanitizedUrl)) {
            throw new FetchException(
                'Invalid URL format',
                context: ['url' => $url]
            );
        }

        try {
            $response = $this->httpFactory
                ->timeout($this->timeout)
                ->get($sanitizedUrl);

            if (! $response->successful()) {
                $this->logger->error('HTTP request failed with status: {status}', [
                    'status' => $response->status(),
                    'url' => $sanitizedUrl,
                ]);

                throw new FetchException(
                    "HTTP request failed with status: {$response->status()}",
                    context: ['status_code' => $response->status(), 'url' => $sanitizedUrl]
                );
            }

            return $response->body();

        } catch (ConnectionException $e) {
            $this->logger->error('HTTP fetch failed: {message}', [
                'message' => $e->getMessage(),
                'url' => $sanitizedUrl,
            ]);

            throw new FetchException(
                'Failed to fetch content from URL',
                previous: $e,
                context: ['url' => $sanitizedUrl, 'original_error' => $e->getMessage()]
            );
        }
    }

    /**
     * Validate if the given string is a valid URL.
     *
     * @param string $url The URL to validate
     *
     * @return bool True if the URL is valid, false otherwise
     */
    protected function isValidUrl(string $url): bool
    {
        $url = $this->convertUnicodeDomainsToAscii($url);

        if (! $this->basicValidation($url)) {
            return false;
        }
        $urlParsed = parse_url($url);

        if ($urlParsed === false) {
            return false;
        }

        return $this->hasValidStructure($urlParsed)
            && $this->hasAllowedScheme($urlParsed)
            && $this->hasSecureHost($urlParsed)
            && $this->hasValidPath($urlParsed);
    }

    /**
     * Sanitize URL by trimming whitespace and ensuring proper format.
     *
     * @param string $url The URL to sanitize
     *
     * @return string The sanitized URL
     */
    protected function sanitizeUrl(string $url): string
    {
        return trim($url);
    }

    /**
     * Check basic URL validation requirements.
     */
    protected function basicValidation(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Check if URL has valid structure with required components.
     */
    protected function hasValidStructure(array $parsed): bool
    {
        return isset($parsed['scheme'], $parsed['host']) && trim($parsed['host']) !== '';
    }

    /**
     * Check if URL uses allowed HTTP/HTTPS schemes only.
     */
    protected function hasAllowedScheme(array $parsed): bool
    {
        return in_array(strtolower($parsed['scheme']), ['http', 'https'], true);
    }

    /**
     * Check if the host is secure and doesn't contain dangerous protocols.
     */
    protected function hasSecureHost(array $parsed): bool
    {
        $host = strtolower($parsed['host']);

        return ! str_contains($host, 'javascript') && ! str_contains($host, 'data');
    }

    /**
     * Check if a path doesn't contain malformed double slashes.
     */
    protected function hasValidPath(array $parsed): bool
    {
        return ! isset($parsed['path']) || ! str_contains($parsed['path'], '//');
    }

    /**
     * Convert Unicode domain names to ASCII (punycode) for validation.
     *
     * @param string $url The URL with a potential Unicode domain
     *
     * @return string The URL with the ASCII domain
     */
    protected function convertUnicodeDomainsToAscii(string $url): string
    {
        if (function_exists('idn_to_ascii')) {
            $parsed = parse_url($url);

            if ($parsed === false || ! isset($parsed['host'])) {
                return $url;
            }
            // Convert the host to ASCII using punycode
            $asciiHost = idn_to_ascii(
                $parsed['host'],
                IDNA_DEFAULT,
                INTL_IDNA_VARIANT_UTS46

            );
            if (
                $asciiHost === false
                || $asciiHost === $parsed['host']
            ) {
                return $url;
            }

            return str_replace($parsed['host'], $asciiHost, $url);
        }

        return $url;
    }
}
