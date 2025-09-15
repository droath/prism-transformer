<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Concerns;

use Droath\PrismTransformer\Exceptions\InvalidInputException;

/**
 * Trait providing input validation methods for transformers.
 *
 * This trait hooks into the beforeTransform method to provide
 * input validation as part of the transformation pipeline.
 */
trait ValidatesInput
{
    /**
     * Hook into the beforeTransform method to validate input.
     *
     * @throws InvalidInputException When validation fails
     */
    protected function beforeTransform(string $content): void
    {
        if (! $this->isValidInput($content)) {
            throw new InvalidInputException('Content validation failed');
        }
        parent::beforeTransform($content);
    }

    /**
     * Validate the input content.
     *
     * Override this method in concrete classes to implement
     * specific validation logic for the transformer.
     */
    abstract protected function isValidInput(string $content): bool;
}
