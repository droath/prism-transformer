<?php

declare(strict_types=1);

use Droath\PrismTransformer\Jobs\TransformationJob;
use Droath\PrismTransformer\Contracts\TransformerInterface;
use Droath\PrismTransformer\Events\TransformationStarted;
use Droath\PrismTransformer\Events\TransformationCompleted;
use Droath\PrismTransformer\Events\TransformationFailed;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\Tests\Stubs\SummarizeTransformer;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\mock;

describe('TransformationJob', function () {
    beforeEach(function () {
        Queue::fake();
        Event::fake();
    });

    describe('job dispatch and queuing', function () {
        test('can be dispatched to queue', function () {
            $transformer = app(SummarizeTransformer::class);
            $content = 'Test content for transformation';
            $context = ['user_id' => 123, 'tenant_id' => 'tenant-1'];

            dispatch(new TransformationJob($transformer, $content, $context));

            Queue::assertPushed(TransformationJob::class, function ($job) use ($content, $context) {
                return $job->content === $content
                    && $job->context === $context;
            });
        });

        test('implements ShouldQueue interface', function () {
            $job = new TransformationJob(
                app(SummarizeTransformer::class),
                'test content',
                []
            );

            expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
        });

        test('has proper queue configuration', function () {
            $job = new TransformationJob(
                app(SummarizeTransformer::class),
                'test content',
                []
            );

            expect($job)->toHaveProperty('tries')
                ->and($job->tries)->toBeGreaterThan(0)
                ->and($job)->toHaveProperty('timeout');
        });
    });

    describe('job constructor', function () {
        test('accepts transformer, content, and context parameters', function () {
            $transformer = app(SummarizeTransformer::class);
            $content = 'Test content for transformation';
            $context = ['user_id' => 123, 'session_id' => 'abc123'];

            $job = new TransformationJob($transformer, $content, $context);

            expect($job->handler)->toBe($transformer)
                ->and($job->content)->toBe($content)
                ->and($job->context)->toBe($context);
        });

        test('accepts closure handler', function () {
            $closure = fn (string $content) => TransformerResult::successful("Processed: {$content}");
            $content = 'Test content for transformation';
            $context = ['user_id' => 123];

            $job = new TransformationJob($closure, $content, $context);

            expect($job->handler)->toBeInstanceOf(\Laravel\SerializableClosure\SerializableClosure::class)
                ->and($job->content)->toBe($content)
                ->and($job->context)->toBe($context);
        });

        test('accepts empty context array', function () {
            $transformer = app(SummarizeTransformer::class);
            $content = 'Test content';

            $job = new TransformationJob($transformer, $content, []);

            expect($job->context)->toBe([]);
        });

        test('accepts complex context data', function () {
            $transformer = app(SummarizeTransformer::class);
            $content = 'Test content';
            $context = [
                'user_id' => 123,
                'tenant_id' => 'tenant-1',
                'metadata' => [
                    'source' => 'api',
                    'priority' => 'high',
                    'tags' => ['urgent', 'customer-request'],
                ],
            ];

            $job = new TransformationJob($transformer, $content, $context);

            expect($job->context)->toBe($context)
                ->and($job->context['metadata']['tags'])->toContain('urgent');
        });
    });

    describe('job serialization and deserialization', function () {
        test('job properties are accessible for serialization', function () {
            $transformer = app(SummarizeTransformer::class);
            $content = 'Test content for serialization';
            $context = ['user_id' => 456];

            $job = new TransformationJob($transformer, $content, $context);

            expect($job->content)->toBe($content)
                ->and($job->context)->toBe($context)
                ->and($job->handler)->toBeInstanceOf(SummarizeTransformer::class);
        });

        test('job can handle serialization requirements', function () {
            $transformer = app(SummarizeTransformer::class);
            $job = new TransformationJob($transformer, 'content', ['user_id' => 789]);

            // Test that job has SerializesModels trait properties
            expect($job)->toHaveProperty('handler')
                ->and($job)->toHaveProperty('content')
                ->and($job)->toHaveProperty('context');
        });
    });

    describe('closure queue functionality', function () {
        test('handles closure execution with successful result', function () {
            $expectedResult = TransformerResult::successful('Closure processed content');
            $closure = fn (string $content) => $expectedResult;

            $content = 'Test content';
            $context = ['user_id' => 123];

            $job = new TransformationJob($closure, $content, $context);
            $job->handle();

            Event::assertDispatched(TransformationStarted::class, function ($event) use ($content, $context) {
                return $event->content === $content
                    && $event->context === $context;
            });

            Event::assertDispatched(TransformationCompleted::class, function ($event) use ($expectedResult, $context) {
                return $event->result === $expectedResult
                    && $event->context === $context;
            });
        });

        test('handles closure that throws exception', function () {
            $exception = new \RuntimeException('Closure processing failed');
            $closure = fn (string $content) => throw $exception;

            $content = 'Test content';
            $context = ['user_id' => 123];

            $job = new TransformationJob($closure, $content, $context);

            expect(fn () => $job->handle())->toThrow(\RuntimeException::class);

            Event::assertDispatched(TransformationStarted::class);
            Event::assertDispatched(TransformationFailed::class, function ($event) use ($exception, $context) {
                return $event->exception === $exception
                    && $event->context === $context;
            });
        });

        test('closure can access and transform content', function () {
            $closure = fn (string $content) => TransformerResult::successful(strtoupper($content));

            $content = 'test content';
            $job = new TransformationJob($closure, $content, []);
            $job->handle();

            Event::assertDispatched(TransformationCompleted::class, function ($event) {
                return $event->result->getContent() === 'TEST CONTENT';
            });
        });

        test('failed method logs closure handler correctly', function () {
            $exception = new \Exception('Closure job failed');
            $closure = fn (string $content) => 'result';

            $job = new TransformationJob($closure, 'content', ['user_id' => 123]);

            Log::shouldReceive('error')
                ->once()
                ->with(
                    'TransformationJob failed after all retry attempts',
                    \Mockery::on(function ($context) use ($exception) {
                        return $context['exception'] === $exception->getMessage()
                            && $context['handler'] === 'Closure'
                            && $context['context']['user_id'] === 123;
                    })
                );

            $job->failed($exception);
        });

    });

    describe('job handle method execution', function () {
        test('executes transformer and dispatches completion event', function () {
            $transformerResult = TransformerResult::successful('Transformed content result');

            $transformer = mock(TransformerInterface::class);
            $transformer->shouldReceive('execute')
                ->once()
                ->with('Test content')
                ->andReturn($transformerResult);

            $content = 'Test content';
            $context = ['user_id' => 123];

            $job = new TransformationJob($transformer, $content, $context);
            $job->handle();

            Event::assertDispatched(TransformationStarted::class, function ($event) use ($content, $context) {
                return $event->content === $content
                    && $event->context === $context;
            });

            Event::assertDispatched(TransformationCompleted::class, function ($event) use ($transformerResult, $context) {
                return $event->result === $transformerResult
                    && $event->context === $context;
            });
        });

        test('dispatches failed event when transformer throws exception', function () {
            $exception = new \Exception('Transformation failed');

            $transformer = mock(TransformerInterface::class);
            $transformer->shouldReceive('execute')
                ->once()
                ->with('Test content')
                ->andThrow($exception);

            $content = 'Test content';
            $context = ['user_id' => 123];

            $job = new TransformationJob($transformer, $content, $context);

            expect(fn () => $job->handle())->toThrow(\Exception::class);

            Event::assertDispatched(TransformationStarted::class);
            Event::assertDispatched(TransformationFailed::class, function ($event) use ($exception, $context) {
                return $event->exception === $exception
                    && $event->context === $context;
            });
        });

        test('preserves context throughout job execution', function () {
            $transformerResult = TransformerResult::successful('Result');
            $transformer = mock(TransformerInterface::class);
            $transformer->shouldReceive('execute')->andReturn($transformerResult);

            $context = [
                'user_id' => 456,
                'tenant_id' => 'tenant-2',
                'request_id' => 'req-789',
            ];

            $job = new TransformationJob($transformer, 'content', $context);
            $job->handle();

            Event::assertDispatched(TransformationStarted::class, function ($event) use ($context) {
                return $event->context === $context;
            });

            Event::assertDispatched(TransformationCompleted::class, function ($event) use ($context) {
                return $event->context === $context;
            });
        });
    });

    describe('job failure handling', function () {
        test('logs failure when job fails', function () {
            $exception = new \RuntimeException('Job processing failed');

            $transformer = mock(TransformerInterface::class);
            $transformer->shouldReceive('execute')->andThrow($exception);

            $job = new TransformationJob($transformer, 'content', ['user_id' => 123]);

            try {
                $job->handle();
            } catch (\Exception $e) {
                // Expected exception
            }

            Event::assertDispatched(TransformationFailed::class);
        });

        test('handles failed method with proper logging', function () {
            $exception = new \Exception('Queue job failed');

            $job = new TransformationJob(
                app(SummarizeTransformer::class),
                'content',
                ['user_id' => 123]
            );

            // Mock the Log facade for this test
            Log::shouldReceive('error')
                ->once()
                ->with(
                    'TransformationJob failed after all retry attempts',
                    \Mockery::on(function ($context) use ($exception) {
                        return $context['exception'] === $exception->getMessage()
                            && $context['context']['user_id'] === 123
                            && isset($context['content_length'])
                            && isset($context['handler']);
                    })
                );

            $job->failed($exception);
        });

        test('dispatches failed event from failed method', function () {
            $exception = new \Exception('Job failure');
            $context = ['user_id' => 789, 'tenant_id' => 'tenant-3'];

            $job = new TransformationJob(
                app(SummarizeTransformer::class),
                'content',
                $context
            );

            $job->failed($exception);

            Event::assertDispatched(TransformationFailed::class, function ($event) use ($exception, $context) {
                return $event->exception === $exception
                    && $event->context === $context;
            });
        });
    });

    describe('queue configuration integration', function () {
        test('respects queue configuration settings', function () {
            config(['prism-transformer.transformation.async_queue' => 'high-priority']);

            $job = new TransformationJob(
                app(SummarizeTransformer::class),
                'content',
                []
            );

            expect($job->queue)->toBe('high-priority');
        });

        test('uses default queue when not configured', function () {
            config(['prism-transformer.transformation.async_queue' => null]);

            $job = new TransformationJob(
                app(SummarizeTransformer::class),
                'content',
                []
            );

            expect($job->queue)->toBeNull();
        });
    });

    describe('error scenarios and edge cases', function () {
        test('handles empty content gracefully', function () {
            $transformer = mock(TransformerInterface::class);
            $transformer->shouldReceive('execute')
                ->with('')
                ->andReturn(TransformerResult::successful(''));

            $job = new TransformationJob($transformer, '', ['user_id' => 123]);
            $job->handle();

            Event::assertDispatched(TransformationStarted::class);
            Event::assertDispatched(TransformationCompleted::class);
        });

        test('handles large content payloads', function () {
            $largeContent = str_repeat('A', 10000); // 10KB content
            $transformer = mock(TransformerInterface::class);
            $transformer->shouldReceive('execute')
                ->with($largeContent)
                ->andReturn(TransformerResult::successful('Summarized'));

            $job = new TransformationJob($transformer, $largeContent, []);
            $job->handle();

            Event::assertDispatched(TransformationCompleted::class);
        });

        test('handles transformer returning failed result', function () {
            $failedResult = TransformerResult::failed(['LLM processing failed']);

            $transformer = mock(TransformerInterface::class);
            $transformer->shouldReceive('execute')->andReturn($failedResult);

            $job = new TransformationJob($transformer, 'content', ['user_id' => 123]);
            $job->handle();

            Event::assertDispatched(TransformationStarted::class);
            Event::assertDispatched(TransformationCompleted::class, function ($event) use ($failedResult) {
                return $event->result === $failedResult;
            });
        });
    });
});
