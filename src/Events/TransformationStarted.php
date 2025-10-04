<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Prism\Prism\ValueObjects\Media\Media;

/**
 * Event dispatched when a transformation job begins execution.
 *
 * This event is fired at the start of both synchronous and asynchronous
 * transformations, providing an opportunity to log, monitor, or react
 * to transformation initiation.
 */
class TransformationStarted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string|\Prism\Prism\ValueObjects\Media\Media|null $content The content being transformed (string or Media object)
     * @param array $context Additional context data (user_id, tenant_id, etc.)
     */
    public function __construct(
        public readonly string|Media|null $content,
        public readonly array $context = []
    ) {}
}
