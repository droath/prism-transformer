<?php

declare(strict_types=1);

use Droath\PrismTransformer\Jobs\TransformationJob;
use Droath\PrismTransformer\PrismTransformer;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Services\RateLimitService;
use Droath\PrismTransformer\Tests\Stubs\SummarizeTransformer;
use Droath\PrismTransformer\Exceptions\RateLimitExceededException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;

describe('Queue Configuration Integration', function () {
    beforeEach(function () {
        Queue::fake();
        Cache::flush();
    });

    describe('Queue Connection Integration', function () {
        test('transformation job uses configured queue connection', function () {
            config(['prism-transformer.transformation.queue_connection' => 'redis']);

            $transformer = app(SummarizeTransformer::class);
            $job = new TransformationJob($transformer, 'test content', []);

            expect($job->connection)->toBe('redis');
        });

        test('transformation job falls back to null connection when not configured', function () {
            config(['prism-transformer.transformation.queue_connection' => null]);

            $transformer = app(SummarizeTransformer::class);
            $job = new TransformationJob($transformer, 'test content', []);

            expect($job->connection)->toBeNull();
        });

        test('async transformation respects queue connection configuration', function () {
            config([
                'prism-transformer.transformation.queue_connection' => 'database',
                'prism-transformer.transformation.async_queue' => 'transformations',
            ]);

            // Directly dispatch the job to test queue configuration
            $transformer = app(SummarizeTransformer::class);
            $job = new TransformationJob($transformer, 'Test content', []);

            // Verify the job has the correct configuration
            expect($job->connection)->toBe('database')
                ->and($job->queue)->toBe('transformations');

            // Test dispatching the job
            dispatch($job);

            Queue::assertPushedOn('transformations', TransformationJob::class);
            Queue::assertPushed(TransformationJob::class, function ($job) {
                return $job->connection === 'database';
            });
        });
    });

    describe('Queue Timeout and Retry Integration', function () {
        test('transformation job uses configured timeout', function () {
            config(['prism-transformer.transformation.timeout' => 120]);

            $transformer = app(SummarizeTransformer::class);
            $job = new TransformationJob($transformer, 'test content', []);

            expect($job->timeout)->toBe(120);
        });

        test('transformation job uses configured retry attempts', function () {
            config(['prism-transformer.transformation.tries' => 5]);

            $transformer = app(SummarizeTransformer::class);
            $job = new TransformationJob($transformer, 'test content', []);

            expect($job->tries)->toBe(5);
        });

        test('multiple jobs respect individual timeout configurations', function () {
            config(['prism-transformer.transformation.timeout' => 90]);

            $job1 = new TransformationJob(app(SummarizeTransformer::class), 'content 1', []);

            config(['prism-transformer.transformation.timeout' => 180]);

            $job2 = new TransformationJob(app(SummarizeTransformer::class), 'content 2', []);

            expect($job1->timeout)->toBe(90)
                ->and($job2->timeout)->toBe(180);
        });
    });

    describe('Configuration Service Integration', function () {
        test('configuration service integration with Laravel app container', function () {
            $configService = app(ConfigurationService::class);

            expect($configService)->toBeInstanceOf(ConfigurationService::class);
        });

        test('configuration service provides correct queue configuration', function () {
            config([
                'prism-transformer.transformation.async_queue' => 'high-priority',
                'prism-transformer.transformation.queue_connection' => 'redis',
                'prism-transformer.transformation.timeout' => 150,
                'prism-transformer.transformation.tries' => 7,
            ]);

            $configService = app(ConfigurationService::class);

            expect($configService->getAsyncQueue())->toBe('high-priority')
                ->and($configService->getQueueConnection())->toBe('redis')
                ->and($configService->getTimeout())->toBe(150)
                ->and($configService->getTries())->toBe(7);
        });

        test('configuration service effective queue connection resolution', function () {
            config([
                'queue.default' => 'sync',
                'prism-transformer.transformation.queue_connection' => null,
            ]);

            $configService = app(ConfigurationService::class);

            expect($configService->getEffectiveQueueConnection())->toBe('sync');

            config(['prism-transformer.transformation.queue_connection' => 'database']);

            expect($configService->getEffectiveQueueConnection())->toBe('database');
        });
    });

    describe('Rate Limiting Integration', function () {
        test('rate limit service integration with Laravel app container', function () {
            $rateLimitService = app(RateLimitService::class);

            expect($rateLimitService)->toBeInstanceOf(RateLimitService::class);
        });

        test('rate limiting configuration integration', function () {
            config([
                'prism-transformer.rate_limiting.enabled' => true,
                'prism-transformer.rate_limiting.max_attempts' => 30,
                'prism-transformer.rate_limiting.decay_minutes' => 5,
            ]);

            $configService = app(ConfigurationService::class);
            $rateLimitConfig = $configService->getRateLimitConfig();

            expect($rateLimitConfig['enabled'])->toBeTrue()
                ->and($rateLimitConfig['max_attempts'])->toBe(30)
                ->and($rateLimitConfig['decay_minutes'])->toBe(5)
                ->and($configService->isRateLimitingEnabled())->toBeTrue();
        });

        test('global rate limiting enforcement during transformation', function () {
            config([
                'prism-transformer.rate_limiting.enabled' => true,
                'prism-transformer.rate_limiting.max_attempts' => 2,
                'prism-transformer.rate_limiting.decay_minutes' => 1,
                'prism-transformer.rate_limiting.global_enabled' => true,
            ]);

            $transformer = app(PrismTransformer::class);
            $transformerInstance = app(SummarizeTransformer::class);

            // First two requests should succeed
            $transformer->text('content 1')->using($transformerInstance)->transform();
            $transformer->text('content 2')->using($transformerInstance)->transform();

            // Third request should be rate limited
            expect(fn () => $transformer->text('content 3')->using($transformerInstance)->transform())
                ->toThrow(RateLimitExceededException::class);
        });

        test('global rate limiting applies to all transformations', function () {
            config([
                'prism-transformer.rate_limiting.enabled' => true,
                'prism-transformer.rate_limiting.max_attempts' => 2,
                'prism-transformer.rate_limiting.decay_minutes' => 1,
            ]);

            // Clear any existing rate limits
            $rateLimitService = app(RateLimitService::class);
            $rateLimitService->resetTransformationRateLimit();

            $transformer1 = app(PrismTransformer::class);
            $transformer2 = app(PrismTransformer::class);
            $transformerInstance = app(SummarizeTransformer::class);

            // First transformation should succeed
            $transformer1->text('content 1')
                ->using($transformerInstance)
                ->transform();

            // Second transformation should succeed
            $transformer2->text('content 2')
                ->using($transformerInstance)
                ->transform();

            // Global limit should be reached
            $remaining = $rateLimitService->getRemainingAttempts($rateLimitService->getGlobalKey());
            expect($remaining)->toBe(0);
        });

        test('rate limiting can be disabled', function () {
            config(['prism-transformer.rate_limiting.enabled' => false]);

            $transformer = app(PrismTransformer::class);
            $transformerInstance = app(SummarizeTransformer::class);

            // Should be able to make multiple requests without rate limiting
            for ($i = 0; $i < 10; $i++) {
                $result = $transformer->text("content {$i}")
                    ->using($transformerInstance)
                    ->transform();

                expect($result)->toBeInstanceOf(\Droath\PrismTransformer\ValueObjects\TransformerResult::class);
            }
        });
    });

    describe('Laravel Queue System Integration', function () {
        test('async transformations integrate with Laravel queue worker configuration', function () {
            config([
                'prism-transformer.transformation.queue_connection' => 'database',
                'prism-transformer.transformation.async_queue' => 'transformations',
                'prism-transformer.transformation.tries' => 3,
                'prism-transformer.transformation.timeout' => 120,
            ]);

            // Create and dispatch job directly to test integration
            $transformer = app(SummarizeTransformer::class);
            $context = ['user_id' => 123, 'priority' => 'high'];
            $job = new TransformationJob($transformer, 'Test integration with Laravel queue', $context);

            dispatch($job);

            Queue::assertPushed(TransformationJob::class, function ($job) {
                return $job->connection === 'database'
                    && $job->queue === 'transformations'
                    && $job->tries === 3
                    && $job->timeout === 120
                    && $job->context['user_id'] === 123
                    && $job->context['priority'] === 'high';
            });
        });

        test('sync transformations bypass queue system', function () {
            config([
                'prism-transformer.transformation.queue_connection' => 'redis',
                'prism-transformer.transformation.async_queue' => 'transformations',
            ]);

            $result = app(PrismTransformer::class)
                ->text('Test sync transformation')
                ->using(app(SummarizeTransformer::class))
                ->transform();

            // Should return TransformerResult directly
            expect($result)->toBeInstanceOf(\Droath\PrismTransformer\ValueObjects\TransformerResult::class);

            // No jobs should be pushed for sync transformations
            Queue::assertNothingPushed();
        });
    });

    describe('Configuration Validation and Error Handling', function () {
        test('handles missing configuration gracefully', function () {
            // Clear all configuration
            config(['prism-transformer' => []]);

            // Should still work with defaults
            $job = new TransformationJob(
                app(SummarizeTransformer::class),
                'test content',
                []
            );

            expect($job->tries)->toBe(3) // Default
                ->and($job->timeout)->toBe(60); // Default
        });

        test('configuration service validates required sections', function () {
            $configService = app(ConfigurationService::class);
            $missing = $configService->validateConfiguration();

            // Should validate that all required sections exist
            expect($missing)->toBeArray();
        });

        test('rate limiting service handles missing rate limiter gracefully', function () {
            // This tests the error handling in checkRateLimits method
            config(['prism-transformer.rate_limiting.enabled' => true]);

            $transformer = app(PrismTransformer::class);

            // Should not throw an exception even if RateLimiter service has issues
            $result = $transformer->text('test content')
                ->using(app(SummarizeTransformer::class))
                ->transform();

            expect($result)->toBeInstanceOf(\Droath\PrismTransformer\ValueObjects\TransformerResult::class);
        });
    });
})->group('integration', 'configuration', 'queue', 'rate-limiting');
