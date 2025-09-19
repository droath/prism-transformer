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

/**
 * Queue job for processing data transformations asynchronously.
 *
 * This job handles the execution of transformer instances in the background,
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
     * @param TransformerInterface $transformer The transformer instance to execute
     * @param string $content The content to transform
     * @param array $context Additional context data (user_id, tenant_id, etc.)
     */
    public function __construct(
        public TransformerInterface $transformer,
        public string $content,
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
            $this->content, $this->context)
        );

        try {
            $result = $this->transformer->execute($this->content);

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
        Log::error('TransformationJob failed after all retry attempts', [
            'exception' => $exception->getMessage(),
            'content_length' => strlen($this->content),
            'context' => $this->context,
            'transformer' => get_class($this->transformer),
        ]);

        Event::dispatch(
            new TransformationFailed($exception, $this->content, $this->context)
        );
    }
}
