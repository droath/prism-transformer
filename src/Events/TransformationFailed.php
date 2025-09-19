<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a transformation job fails.
 *
 * This event is fired when a transformation job encounters an exception
 * during execution, providing access to the exception details and context
 * for error handling and monitoring.
 */
class TransformationFailed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param \Exception $exception The exception that caused the failure
     * @param string $content The content that was being transformed
     * @param array $context Additional context data (user_id, tenant_id, etc.)
     */
    public function __construct(
        public readonly \Exception $exception,
        public readonly string $content,
        public readonly array $context = []
    ) {}
}
