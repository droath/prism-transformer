<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Jobs;

use Droath\PrismTransformer\Contracts\TransformerInterface;
use Droath\PrismTransformer\Events\TransformationStarted;
use Droath\PrismTransformer\Events\TransformationCompleted;
use Droath\PrismTransformer\Events\TransformationFailed;
use Droath\PrismTransformer\Services\ConfigurationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\SerializableClosure\SerializableClosure;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\Exceptions\TransformerException;

/**
 * Queue job for processing data transformations asynchronously.
 *
 * This job handles the execution of transformer instances and closures in the background,
 * providing event dispatching, error handling, and context preservation
 * throughout the transformation pipeline.
 */
class TransformationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout;

    /**
     * Create a new job instance.
     *
     * @param \Droath\PrismTransformer\Contracts\TransformerInterface|\Closure|\Laravel\SerializableClosure\SerializableClosure $handler The transformer instance or closure to execute
     * @param string|null $content The content to transform (Media objects are converted to base64 strings before queuing)
     * @param array $context Additional context data (user_id, tenant_id, etc.)
     */
    public function __construct(
        public TransformerInterface|\Closure|SerializableClosure $handler,
        public ?string $content,
        public array $context = []
    ) {
        $configService = app(ConfigurationService::class);

        $this->tries = $configService->getTries();
        $this->timeout = $configService->getTimeout();

        if ($queueName = $configService->getAsyncQueue()) {
            $this->onQueue($queueName);
        }

        if ($queueConnection = $configService->getQueueConnection()) {
            $this->onConnection($queueConnection);
        }

        if ($this->handler instanceof \Closure) {
            $this->handler = new SerializableClosure($this->handler);
        }
    }

    /**
     * Execute the job.
     *
     * Dispatches transformation events and executes the transformer,
     * handling both successful and failed scenarios.
     */
    public function handle(): void
    {
        Event::dispatch(new TransformationStarted(
            $this->content,
            $this->context
        ));

        try {
            $result = null;

            if (is_callable($this->handler)) {
                $result = ($this->handler)($this->content, $this->context);
            }

            if ($this->handler instanceof TransformerInterface) {
                $result = $this->handler->execute($this->content, $this->context);
            }

            if (! $result instanceof TransformerResult) {
                throw new TransformerException(
                    'The transformer result is a TransformerResult instance.'
                );
            }

            Event::dispatch(
                new TransformationCompleted($result, $this->context)
            );
        } catch (\Exception $exception) {
            Event::dispatch(new TransformationFailed(
                $exception,
                $this->content,
                $this->context
            ));

            throw $exception;
        }
    }

    /**
     * Handle a job failure.
     *
     * Laravel calls this method when the job fails after all retry attempts
     * have been exhausted.
     *
     * @param \Exception $exception The exception that caused the failure
     */
    public function failed(\Exception $exception): void
    {
        $handlerType = match (true) {
            $this->handler instanceof \Closure, $this->handler instanceof SerializableClosure => 'Closure',
            default => get_class($this->handler),
        };

        Log::error('TransformationJob failed after all retry attempts', [
            'exception' => $exception->getMessage(),
            'content_type' => is_string($this->content) ? 'string' : get_class($this->content),
            'content_length' => is_string($this->content) ? strlen($this->content) : null,
            'context' => $this->context,
            'handler' => $handlerType,
        ]);

        Event::dispatch(
            new TransformationFailed($exception, $this->content, $this->context)
        );
    }
}
