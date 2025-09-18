<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Events;

use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a transformation completes successfully.
 *
 * This event is fired when a transformation job finishes execution,
 * regardless of whether the transformation itself was successful or
 * failed (check the TransformerResult for transformation status).
 */
class TransformationCompleted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param TransformerResult $result The transformation result
     * @param array $context Additional context data (user_id, tenant_id, etc.)
     */
    public function __construct(
        public readonly TransformerResult $result,
        public readonly array $context = []
    ) {}
}
