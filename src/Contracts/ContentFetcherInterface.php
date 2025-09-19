<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Contracts;

use Droath\PrismTransformer\Exceptions\FetchException;

/**
 * Interface for fetching content from various sources.
 *
 * This interface provides a standardized way to retrieve content from different
 * sources like URLs, files, or other data sources for transformation processing.
 */
interface ContentFetcherInterface
{
    /**
     * Fetch content from the given URL.
     *
     * @param string $url The URL to fetch content from
     * @param array $options Optional configuration for the fetch operation
     *
     * @return string The fetched content as a string
     *
     * @throws FetchException When content cannot be fetched from the URL
     */
    public function fetch(string $url, array $options = []): string;
}
