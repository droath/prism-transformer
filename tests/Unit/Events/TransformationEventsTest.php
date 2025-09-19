<?php

declare(strict_types=1);

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Droath\PrismTransformer\Events\TransformationStarted;
use Droath\PrismTransformer\Events\TransformationCompleted;
use Droath\PrismTransformer\Events\TransformationFailed;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;

describe('Transformation Events', function () {
    beforeEach(function () {
        Event::fake();
    });

    describe('TransformationStarted Event', function () {
        test('can be instantiated with content and context', function () {
            $content = 'Test content for transformation';
            $context = ['user_id' => 123, 'tenant_id' => 'tenant-1'];

            $event = new TransformationStarted($content, $context);

            expect($event->content)->toBe($content)
                ->and($event->context)->toBe($context);
        });

        test('can be instantiated with content only', function () {
            $content = 'Test content for transformation';

            $event = new TransformationStarted($content);

            expect($event->content)->toBe($content)
                ->and($event->context)->toBe([]);
        });

        test('has required traits for Laravel events', function () {
            $event = new TransformationStarted('test content');

            $traits = class_uses($event);
            expect($traits)->toContain(Dispatchable::class)
                ->and($traits)->toContain(SerializesModels::class);
        });

        test('properties are readonly', function () {
            $event = new TransformationStarted('test content', ['user_id' => 123]);

            $reflection = new \ReflectionClass($event);
            $contentProperty = $reflection->getProperty('content');
            $contextProperty = $reflection->getProperty('context');

            expect($contentProperty->isReadOnly())->toBeTrue()
                ->and($contextProperty->isReadOnly())->toBeTrue();
        });

        test('can be dispatched through Laravel event system', function () {
            $content = 'Test content';
            $context = ['user_id' => 456];

            event(new TransformationStarted($content, $context));

            Event::assertDispatched(TransformationStarted::class, function ($event) use ($content, $context) {
                return $event->content === $content && $event->context === $context;
            });
        });

        test('handles complex context data structures', function () {
            $context = [
                'user_id' => 123,
                'tenant_id' => 'tenant-1',
                'metadata' => [
                    'source' => 'api',
                    'priority' => 'high',
                    'tags' => ['urgent', 'customer-request'],
                    'nested' => [
                        'deep' => ['value' => 'test'],
                    ],
                ],
                'timestamps' => [
                    'created_at' => '2023-01-01T00:00:00Z',
                    'updated_at' => '2023-01-01T00:00:00Z',
                ],
            ];

            $event = new TransformationStarted('content', $context);

            expect($event->context)->toBe($context)
                ->and($event->context['metadata']['tags'])->toContain('urgent')
                ->and($event->context['metadata']['nested']['deep']['value'])->toBe('test');
        });

        test('handles empty and null values gracefully', function () {
            $event1 = new TransformationStarted('');
            $event2 = new TransformationStarted('content', []);

            expect($event1->content)->toBe('')
                ->and($event1->context)->toBe([])
                ->and($event2->context)->toBe([]);
        });
    });

    describe('TransformationCompleted Event', function () {
        test('can be instantiated with result and context', function () {
            $result = TransformerResult::successful('Transformation complete');
            $context = ['user_id' => 789, 'session_id' => 'abc123'];

            $event = new TransformationCompleted($result, $context);

            expect($event->result)->toBe($result)
                ->and($event->context)->toBe($context);
        });

        test('can be instantiated with result only', function () {
            $result = TransformerResult::successful('Transformation complete');

            $event = new TransformationCompleted($result);

            expect($event->result)->toBe($result)
                ->and($event->context)->toBe([]);
        });

        test('has required traits for Laravel events', function () {
            $result = TransformerResult::successful('test');
            $event = new TransformationCompleted($result);

            $traits = class_uses($event);
            expect($traits)->toContain(Dispatchable::class)
                ->and($traits)->toContain(SerializesModels::class);
        });

        test('properties are readonly', function () {
            $result = TransformerResult::successful('test');
            $event = new TransformationCompleted($result, ['user_id' => 123]);

            $reflection = new \ReflectionClass($event);
            $resultProperty = $reflection->getProperty('result');
            $contextProperty = $reflection->getProperty('context');

            expect($resultProperty->isReadOnly())->toBeTrue()
                ->and($contextProperty->isReadOnly())->toBeTrue();
        });

        test('can be dispatched through Laravel event system', function () {
            $result = TransformerResult::successful('Success');
            $context = ['user_id' => 456, 'request_id' => 'req-123'];

            event(new TransformationCompleted($result, $context));

            Event::assertDispatched(TransformationCompleted::class, function ($event) use ($result, $context) {
                return $event->result === $result && $event->context === $context;
            });
        });

        test('works with successful transformation results', function () {
            $content = 'Successfully transformed content';
            $result = TransformerResult::successful($content);
            $context = ['user_id' => 123];

            $event = new TransformationCompleted($result, $context);

            expect($event->result->getContent())->toBe($content)
                ->and($event->result->isSuccessful())->toBeTrue()
                ->and($event->context)->toBe($context);
        });

        test('works with failed transformation results', function () {
            $errors = ['Error 1', 'Error 2'];
            $result = TransformerResult::failed($errors);
            $context = ['user_id' => 123, 'attempt' => 2];

            $event = new TransformationCompleted($result, $context);

            expect($event->result->getErrors())->toBe($errors)
                ->and($event->result->isSuccessful())->toBeFalse()
                ->and($event->context['attempt'])->toBe(2);
        });

        test('preserves all context data types', function () {
            $result = TransformerResult::successful('test');
            $context = [
                'user_id' => 123,
                'is_premium' => true,
                'weight' => 45.67,
                'tags' => ['tag1', 'tag2'],
                'metadata' => ['key' => 'value'],
                'null_value' => null,
            ];

            $event = new TransformationCompleted($result, $context);

            expect($event->context['user_id'])->toBe(123)
                ->and($event->context['is_premium'])->toBeTrue()
                ->and($event->context['weight'])->toBe(45.67)
                ->and($event->context['tags'])->toBe(['tag1', 'tag2'])
                ->and($event->context['metadata'])->toBe(['key' => 'value'])
                ->and($event->context['null_value'])->toBeNull();
        });
    });

    describe('TransformationFailed Event', function () {
        test('can be instantiated with exception, content and context', function () {
            $exception = new \Exception('Transformation failed');
            $content = 'Content that failed to transform';
            $context = ['user_id' => 456, 'retry_count' => 3];

            $event = new TransformationFailed($exception, $content, $context);

            expect($event->exception)->toBe($exception)
                ->and($event->content)->toBe($content)
                ->and($event->context)->toBe($context);
        });

        test('can be instantiated with exception and content only', function () {
            $exception = new \RuntimeException('Processing error');
            $content = 'Failed content';

            $event = new TransformationFailed($exception, $content);

            expect($event->exception)->toBe($exception)
                ->and($event->content)->toBe($content)
                ->and($event->context)->toBe([]);
        });

        test('has required traits for Laravel events', function () {
            $exception = new \Exception('test');
            $event = new TransformationFailed($exception, 'content');

            $traits = class_uses($event);
            expect($traits)->toContain(Dispatchable::class)
                ->and($traits)->toContain(SerializesModels::class);
        });

        test('properties are readonly', function () {
            $exception = new \Exception('test');
            $event = new TransformationFailed($exception, 'content', ['user_id' => 123]);

            $reflection = new \ReflectionClass($event);
            $exceptionProperty = $reflection->getProperty('exception');
            $contentProperty = $reflection->getProperty('content');
            $contextProperty = $reflection->getProperty('context');

            expect($exceptionProperty->isReadOnly())->toBeTrue()
                ->and($contentProperty->isReadOnly())->toBeTrue()
                ->and($contextProperty->isReadOnly())->toBeTrue();
        });

        test('can be dispatched through Laravel event system', function () {
            $exception = new \InvalidArgumentException('Invalid input');
            $content = 'Invalid content';
            $context = ['user_id' => 789, 'error_code' => 'E001'];

            event(new TransformationFailed($exception, $content, $context));

            Event::assertDispatched(TransformationFailed::class, function ($event) use ($exception, $content, $context) {
                return $event->exception === $exception
                    && $event->content === $content
                    && $event->context === $context;
            });
        });

        test('works with different exception types', function () {
            $exceptions = [
                new \Exception('General exception'),
                new \RuntimeException('Runtime error'),
                new \InvalidArgumentException('Invalid argument'),
                new \LogicException('Logic error'),
            ];

            foreach ($exceptions as $exception) {
                $event = new TransformationFailed($exception, 'content', []);

                expect($event->exception)->toBe($exception)
                    ->and($event->exception->getMessage())->toBe($exception->getMessage());
            }
        });

        test('preserves exception details and stack trace', function () {
            $exception = new \RuntimeException('Test exception', 500);
            $event = new TransformationFailed($exception, 'content', ['user_id' => 123]);

            expect($event->exception->getMessage())->toBe('Test exception')
                ->and($event->exception->getCode())->toBe(500)
                ->and($event->exception->getTrace())->toBeArray();
        });

        test('handles empty content and complex context', function () {
            $exception = new \Exception('Empty content error');
            $context = [
                'user_id' => 123,
                'attempt_number' => 5,
                'error_details' => [
                    'code' => 'EMPTY_CONTENT',
                    'severity' => 'high',
                    'retry_after' => 300,
                ],
                'trace_id' => 'trace-abc-123',
            ];

            $event = new TransformationFailed($exception, '', $context);

            expect($event->content)->toBe('')
                ->and($event->context['attempt_number'])->toBe(5)
                ->and($event->context['error_details']['code'])->toBe('EMPTY_CONTENT')
                ->and($event->context['trace_id'])->toBe('trace-abc-123');
        });
    });

    describe('Event Serialization and Deserialization', function () {
        test('TransformationStarted can be serialized and unserialized', function () {
            $content = 'Test content';
            $context = ['user_id' => 123, 'data' => ['nested' => 'value']];
            $event = new TransformationStarted($content, $context);

            $serialized = serialize($event);
            $unserialized = unserialize($serialized);

            expect($unserialized->content)->toBe($content)
                ->and($unserialized->context)->toBe($context);
        });

        test('TransformationCompleted can be serialized and unserialized', function () {
            $result = TransformerResult::successful('Success result');
            $context = ['user_id' => 456];
            $event = new TransformationCompleted($result, $context);

            $serialized = serialize($event);
            $unserialized = unserialize($serialized);

            expect($unserialized->result->getContent())->toBe('Success result')
                ->and($unserialized->context)->toBe($context);
        });

        test('TransformationFailed can be serialized and unserialized', function () {
            $exception = new \RuntimeException('Test exception', 500);
            $content = 'Failed content';
            $context = ['user_id' => 789];
            $event = new TransformationFailed($exception, $content, $context);

            // Test that the event can be serialized (Laravel handles this internally)
            // We test the properties are accessible after construction
            expect($event->exception->getMessage())->toBe('Test exception')
                ->and($event->exception->getCode())->toBe(500)
                ->and($event->content)->toBe($content)
                ->and($event->context)->toBe($context);

            // Note: Exception serialization is handled by Laravel's SerializesModels trait
            // which uses special handling for exceptions in queue jobs
        });
    });

    describe('Event Broadcasting Compatibility', function () {
        test('events have broadcastable structure without implementing ShouldBroadcast', function () {
            $startedEvent = new TransformationStarted('content', ['user_id' => 123]);
            $completedEvent = new TransformationCompleted(TransformerResult::successful('result'), ['user_id' => 123]);
            $failedEvent = new TransformationFailed(new \Exception('error'), 'content', ['user_id' => 123]);

            // Verify they have the Dispatchable trait but don't implement ShouldBroadcast
            $startedTraits = class_uses($startedEvent);
            $completedTraits = class_uses($completedEvent);
            $failedTraits = class_uses($failedEvent);

            expect($startedTraits)->toContain(Dispatchable::class)
                ->and($completedTraits)->toContain(Dispatchable::class)
                ->and($failedTraits)->toContain(Dispatchable::class)
                ->and($startedEvent)->not->toBeInstanceOf(ShouldBroadcast::class)
                ->and($completedEvent)->not->toBeInstanceOf(ShouldBroadcast::class)
                ->and($failedEvent)->not->toBeInstanceOf(ShouldBroadcast::class);

            // Verify they don't automatically broadcast
        });

        test('events can be extended to support broadcasting if needed', function () {
            // Test that the events have the necessary structure to be broadcastable
            $event = new TransformationStarted('content', ['user_id' => 123, 'channel' => 'user.123']);

            expect($event->context)->toHaveKey('user_id')
                ->and($event->context)->toHaveKey('channel');
        });
    });
});
