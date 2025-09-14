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
     * Fetch content from the given source.
     *
     * @param mixed $source The source to fetch content from (URL, file path, etc.)
     *
     * @return string The fetched content as a string
     *
     * @throws FetchException When content cannot be fetched from the source
     */
    public function fetch(mixed $source): string;
}
