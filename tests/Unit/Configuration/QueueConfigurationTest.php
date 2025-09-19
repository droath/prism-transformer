<?php

declare(strict_types=1);

use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Jobs\TransformationJob;
use Droath\PrismTransformer\Tests\Stubs\SummarizeTransformer;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('Queue Configuration', function () {
    beforeEach(function () {
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
    });

    describe('Configuration Service Integration', function () {
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
    });

    describe('Configuration Inheritance', function () {
        test('inherits Laravel queue configuration when package connection is null', function () {
            config([
                'queue.default' => 'redis',
                'prism-transformer.transformation.queue_connection' => null,
            ]);

            $configService = app(ConfigurationService::class);

            // Should fall back to Laravel's default queue connection
            expect($configService->getEffectiveQueueConnection())->toBe('redis');
        });

        test('overrides Laravel queue configuration when package connection is set', function () {
            config([
                'queue.default' => 'sync',
                'prism-transformer.transformation.queue_connection' => 'database',
            ]);

            $configService = app(ConfigurationService::class);

            expect($configService->getEffectiveQueueConnection())->toBe('database');
        });
    });
})->group('configuration', 'queue', 'unit');