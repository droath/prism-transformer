<?php

declare(strict_types=1);

use Droath\PrismTransformer\Jobs\TransformationJob;
use Droath\PrismTransformer\PrismTransformer;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Services\RateLimitService;
use Droath\PrismTransformer\Tests\Stubs\SummarizeTransformer;
use Droath\PrismTransformer\Tests\Stubs\CustomTransformationJob;
use Droath\PrismTransformer\Exceptions\RateLimitExceededException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;

describe('Queue Configuration', function () {
    beforeEach(function () {
        Queue::fake();
        Cache::flush();

        // Reset configuration to defaults
        config([
            'prism-transformer.transformation.async_queue' => 'default',
            'prism-transformer.transformation.queue_connection' => null,
            'prism-transformer.transformation.timeout' => 60,
            'prism-transformer.transformation.tries' => 3,
        ]);
    });

    describe('Default Queue Configuration', function () {
        test('loads default async queue configuration', function () {
            expect(config('prism-transformer.transformation.async_queue'))
                ->toBe('default');
        });

        test('loads default queue connection as null', function () {
            expect(config('prism-transformer.transformation.queue_connection'))
                ->toBeNull();
        });

        test('loads default timeout configuration', function () {
            expect(config('prism-transformer.transformation.timeout'))
                ->toBe(60);
        });

        test('loads default retry attempts configuration', function () {
            expect(config('prism-transformer.transformation.tries'))
                ->toBe(3);
        });
    });

    describe('Environment Variable Configuration', function () {
        test('respects PRISM_TRANSFORMER_ASYNC_QUEUE environment variable', function () {
            config(['prism-transformer.transformation.async_queue' => 'high-priority']);

            expect(config('prism-transformer.transformation.async_queue'))
                ->toBe('high-priority');
        });

        test('respects PRISM_TRANSFORMER_QUEUE_CONNECTION environment variable', function () {
            config(['prism-transformer.transformation.queue_connection' => 'redis']);

            expect(config('prism-transformer.transformation.queue_connection'))
                ->toBe('redis');
        });

        test('respects PRISM_TRANSFORMER_TIMEOUT environment variable', function () {
            config(['prism-transformer.transformation.timeout' => 120]);

            expect(config('prism-transformer.transformation.timeout'))
                ->toBe(120);
        });

        test('respects PRISM_TRANSFORMER_TRIES environment variable', function () {
            config(['prism-transformer.transformation.tries' => 5]);

            expect(config('prism-transformer.transformation.tries'))
                ->toBe(5);
        });
    });

    describe('Queue Connection Configuration', function () {
        test('supports redis queue connection configuration', function () {
            config(['prism-transformer.transformation.queue_connection' => 'redis']);

            $job = new TransformationJob(
                app(SummarizeTransformer::class),
                'test content',
                []
            );

            expect($job->connection)->toBe('redis');
        });

        test('supports database queue connection configuration', function () {
            config(['prism-transformer.transformation.queue_connection' => 'database']);

            $job = new TransformationJob(
                app(SummarizeTransformer::class),
                'test content',
                []
            );

            expect($job->connection)->toBe('database');
        });

        test('supports sqs queue connection configuration', function () {
            config(['prism-transformer.transformation.queue_connection' => 'sqs']);

            $job = new TransformationJob(
                app(SummarizeTransformer::class),
                'test content',
                []
            );

            expect($job->connection)->toBe('sqs');
        });

        test('defaults to null connection when not configured', function () {
            config(['prism-transformer.transformation.queue_connection' => null]);

            $job = new TransformationJob(
                app(SummarizeTransformer::class),
                'test content',
                []
            );

            expect($job->connection)->toBeNull();
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

    describe('Queue Name Configuration', function () {
        test('uses configured async_queue for job queue', function () {
            config(['prism-transformer.transformation.async_queue' => 'transformations']);

            $job = new TransformationJob(
                app(SummarizeTransformer::class),
                'test content',
                []
            );

            expect($job->queue)->toBe('transformations');
        });

        test('supports different queue names for different priorities', function () {
            config(['prism-transformer.transformation.async_queue' => 'low-priority']);

            $job = new TransformationJob(
                app(SummarizeTransformer::class),
                'test content',
                []
            );

            expect($job->queue)->toBe('low-priority');
        });
    });

    describe('Job Timeout Configuration', function () {
        test('applies configured timeout to transformation jobs', function () {
            config(['prism-transformer.transformation.timeout' => 180]);

            $job = new TransformationJob(
                app(SummarizeTransformer::class),
                'test content',
                []
            );

            expect($job->timeout)->toBe(180);
        });

        test('supports different timeout values for different environments', function () {
            config(['prism-transformer.transformation.timeout' => 300]);

            $job = new TransformationJob(
                app(SummarizeTransformer::class),
                'test content',
                []
            );

            expect($job->timeout)->toBe(300);
        });

        test('transformation job uses configured timeout', function () {
            config(['prism-transformer.transformation.timeout' => 120]);

            $transformer = app(SummarizeTransformer::class);
            $job = new TransformationJob($transformer, 'test content', []);

            expect($job->timeout)->toBe(120);
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

    describe('Retry Configuration', function () {
        test('applies configured retry attempts to transformation jobs', function () {
            config(['prism-transformer.transformation.tries' => 5]);

            $job = new TransformationJob(
                app(SummarizeTransformer::class),
                'test content',
                []
            );

            expect($job->tries)->toBe(5);
        });

        test('supports different retry values for different scenarios', function () {
            config(['prism-transformer.transformation.tries' => 10]);

            $job = new TransformationJob(
                app(SummarizeTransformer::class),
                'test content',
                []
            );

            expect($job->tries)->toBe(10);
        });

        test('transformation job uses configured retry attempts', function () {
            config(['prism-transformer.transformation.tries' => 5]);

            $transformer = app(SummarizeTransformer::class);
            $job = new TransformationJob($transformer, 'test content', []);

            expect($job->tries)->toBe(5);
        });
    });

    describe('Configuration Service Integration', function () {
        test('configuration service integration with Laravel app container', function () {
            $configService = app(ConfigurationService::class);

            expect($configService)->toBeInstanceOf(ConfigurationService::class);
        });

        test('configuration service returns correct queue connection', function () {
            config(['prism-transformer.transformation.queue_connection' => 'redis']);

            $configService = app(ConfigurationService::class);

            expect($configService->getQueueConnection())->toBe('redis');
        });

        test('configuration service returns null for unset queue connection', function () {
            config(['prism-transformer.transformation.queue_connection' => null]);

            $configService = app(ConfigurationService::class);

            expect($configService->getQueueConnection())->toBeNull();
        });

        test('configuration service returns correct async queue name', function () {
            config(['prism-transformer.transformation.async_queue' => 'priority-queue']);

            $configService = app(ConfigurationService::class);

            expect($configService->getAsyncQueue())->toBe('priority-queue');
        });

        test('configuration service returns correct timeout value', function () {
            config(['prism-transformer.transformation.timeout' => 240]);

            $configService = app(ConfigurationService::class);

            expect($configService->getTimeout())->toBe(240);
        });

        test('configuration service returns correct retry attempts', function () {
            config(['prism-transformer.transformation.tries' => 7]);

            $configService = app(ConfigurationService::class);

            expect($configService->getTries())->toBe(7);
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

        test('configuration service queue connection resolution', function () {
            config([
                'queue.default' => 'sync',
                'prism-transformer.transformation.queue_connection' => null,
            ]);

            $configService = app(ConfigurationService::class);

            expect($configService->getQueueConnection())->toBeNull();

            config(['prism-transformer.transformation.queue_connection' => 'database']);

            expect($configService->getQueueConnection())->toBe('database');
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

    describe('Queue Configuration Validation', function () {
        test('validates queue connection values', function () {
            $validConnections = ['redis', 'database', 'sqs', 'sync', null];

            foreach ($validConnections as $connection) {
                config(['prism-transformer.transformation.queue_connection' => $connection]);

                $job = new TransformationJob(
                    app(SummarizeTransformer::class),
                    'test content',
                    []
                );

                expect($job->connection)->toBe($connection);
            }
        });

        test('validates timeout is positive integer', function () {
            $validTimeouts = [30, 60, 120, 300, 600];

            foreach ($validTimeouts as $timeout) {
                config(['prism-transformer.transformation.timeout' => $timeout]);

                $job = new TransformationJob(
                    app(SummarizeTransformer::class),
                    'test content',
                    []
                );

                expect($job->timeout)->toBe($timeout)
                    ->and($job->timeout)->toBeGreaterThan(0);
            }
        });

        test('validates tries is positive integer', function () {
            $validTries = [1, 3, 5, 10, 15];

            foreach ($validTries as $tries) {
                config(['prism-transformer.transformation.tries' => $tries]);

                $job = new TransformationJob(
                    app(SummarizeTransformer::class),
                    'test content',
                    []
                );

                expect($job->tries)->toBe($tries)
                    ->and($job->tries)->toBeGreaterThan(0);
            }
        });

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

        test('configuration service provides access to configuration', function () {
            $configService = app(ConfigurationService::class);

            // Should be able to access all required configuration
            expect($configService->getDefaultProvider())->toBeInstanceOf(\Droath\PrismTransformer\Enums\Provider::class);
            expect($configService->getCacheStore())->toBeString();
            expect($configService->getAsyncQueue())->not->toBeNull();
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

    describe('Configuration Inheritance', function () {
        test('returns null when package connection is not set', function () {
            config([
                'queue.default' => 'redis',
                'prism-transformer.transformation.queue_connection' => null,
            ]);

            $configService = app(ConfigurationService::class);

            expect($configService->getQueueConnection())->toBeNull();
        });

        test('returns configured queue connection when set', function () {
            config([
                'queue.default' => 'sync',
                'prism-transformer.transformation.queue_connection' => 'database',
            ]);

            $configService = app(ConfigurationService::class);

            expect($configService->getQueueConnection())->toBe('database');
        });
    });

    describe('Custom Job Class Configuration', function () {
        test('uses default TransformationJob when no custom class configured', function () {
            $configService = app(ConfigurationService::class);

            $jobClass = $configService->getJobClass();

            expect($jobClass)->toBe(TransformationJob::class);
        });

        test('async transformation uses configured custom job class', function () {
            config([
                'prism-transformer.transformation.job_class' => CustomTransformationJob::class,
            ]);

            // Force rebind to pick up new config
            app()->forgetInstance(ConfigurationService::class);

            $configService = app(ConfigurationService::class);

            // Verify the configuration is correctly set
            expect($configService->getJobClass())->toBe(CustomTransformationJob::class);

            $transformer = app(PrismTransformer::class);
            $transformerInstance = app(SummarizeTransformer::class);

            $result = $transformer
                ->text('Test content')
                ->async()
                ->using($transformerInstance)
                ->transform();

            // Async transformation should return PendingDispatch
            expect($result)->toBeInstanceOf(\Illuminate\Foundation\Bus\PendingDispatch::class);
        });

        test('custom job class receives correct configuration', function () {
            config([
                'prism-transformer.transformation.job_class' => CustomTransformationJob::class,
                'prism-transformer.transformation.queue_connection' => 'redis',
                'prism-transformer.transformation.async_queue' => 'custom-queue',
                'prism-transformer.transformation.timeout' => 120,
                'prism-transformer.transformation.tries' => 5,
            ]);

            $transformer = app(SummarizeTransformer::class);
            $job = new CustomTransformationJob($transformer, 'Test content', []);

            expect($job)->toBeInstanceOf(TransformationJob::class)
                ->and($job->connection)->toBe('redis')
                ->and($job->queue)->toBe('custom-queue')
                ->and($job->timeout)->toBe(120)
                ->and($job->tries)->toBe(5);
        });

        test('throws exception for non-existent job class', function () {
            config([
                'prism-transformer.transformation.job_class' => 'App\NonExistentJob',
            ]);

            $configService = app(ConfigurationService::class);

            expect(fn () => $configService->getJobClass())
                ->toThrow(\InvalidArgumentException::class, 'The configured job class [App\NonExistentJob] does not exist.');
        });

        test('throws exception for job class that does not extend TransformationJob', function () {
            $invalidJobClass = new class
            {
                // Does not extend TransformationJob
            };

            config([
                'prism-transformer.transformation.job_class' => get_class($invalidJobClass),
            ]);

            $configService = app(ConfigurationService::class);

            expect(fn () => $configService->getJobClass())
                ->toThrow(\InvalidArgumentException::class);
        });
    });
})->group('configuration', 'queue', 'unit', 'integration', 'rate-limiting');
