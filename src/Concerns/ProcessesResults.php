<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Concerns;

use Droath\PrismTransformer\ValueObjects\TransformerResult;

/**
 * Trait providing result processing methods for transformers.
 *
 * This trait hooks into the afterTransform method to provide
 * result processing as part of the transformation pipeline.
 */
trait ProcessesResults
{
    /**
     * Hook into the afterTransform method to process results.
     */
    protected function afterTransform(TransformerResult $result): void
    {
        if ($result->isSuccessful()) {
            $this->processSuccessfulResult($result);
        }
        parent::afterTransform($result);
    }

    /**
     * Process successful transformation results.
     *
     * Override this method in concrete classes to implement
     * specific result processing logic.
     */
    protected function processSuccessfulResult(TransformerResult $result): void {}
}
