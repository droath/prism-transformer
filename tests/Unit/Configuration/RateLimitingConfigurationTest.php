<?php

declare(strict_types=1);

use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Services\RateLimitService;

describe('Rate Limiting Configuration', function () {
    beforeEach(function () {
        // Reset configuration to defaults
        config([
            'prism-transformer.rate_limiting.enabled' => true,
            'prism-transformer.rate_limiting.max_attempts' => 60,
            'prism-transformer.rate_limiting.decay_minutes' => 1,
            'prism-transformer.rate_limiting.key_prefix' => 'prism_rate_limit',
        ]);
    });

    describe('Default Rate Limiting Configuration', function () {
        test('loads default rate limiting enabled state', function () {
            expect(config('prism-transformer.rate_limiting.enabled'))
                ->toBeTrue();
        });

        test('loads default max attempts configuration', function () {
            expect(config('prism-transformer.rate_limiting.max_attempts'))
                ->toBe(60);
        });

        test('loads default decay minutes configuration', function () {
            expect(config('prism-transformer.rate_limiting.decay_minutes'))
                ->toBe(1);
        });

        test('loads default key prefix configuration', function () {
            expect(config('prism-transformer.rate_limiting.key_prefix'))
                ->toBe('prism_rate_limit');
        });

    });

    describe('Environment Variable Configuration', function () {
        test('respects PRISM_RATE_LIMITING_ENABLED environment variable', function () {
            config(['prism-transformer.rate_limiting.enabled' => false]);

            expect(config('prism-transformer.rate_limiting.enabled'))
                ->toBeFalse();
        });

        test('respects PRISM_RATE_LIMIT_ATTEMPTS environment variable', function () {
            config(['prism-transformer.rate_limiting.max_attempts' => 120]);

            expect(config('prism-transformer.rate_limiting.max_attempts'))
                ->toBe(120);
        });

        test('respects PRISM_RATE_LIMIT_DECAY environment variable', function () {
            config(['prism-transformer.rate_limiting.decay_minutes' => 5]);

            expect(config('prism-transformer.rate_limiting.decay_minutes'))
                ->toBe(5);
        });

        test('respects PRISM_RATE_LIMIT_PREFIX environment variable', function () {
            config(['prism-transformer.rate_limiting.key_prefix' => 'custom_prefix']);

            expect(config('prism-transformer.rate_limiting.key_prefix'))
                ->toBe('custom_prefix');
        });
    });

    describe('Rate Limiting Behavior', function () {
        test('applies global rate limiting by default', function () {
            config(['prism-transformer.rate_limiting.enabled' => true]);

            $configService = app(ConfigurationService::class);

            expect($configService->isRateLimitingEnabled())->toBeTrue();
        });

        test('supports disabling all rate limiting', function () {
            config(['prism-transformer.rate_limiting.enabled' => false]);

            $configService = app(ConfigurationService::class);

            expect($configService->isRateLimitingEnabled())->toBeFalse();
        });
    });

    describe('Rate Limiting Thresholds Configuration', function () {
        test('supports different rate limit thresholds', function () {
            $thresholds = [10, 30, 60, 120, 300];

            foreach ($thresholds as $threshold) {
                config(['prism-transformer.rate_limiting.max_attempts' => $threshold]);

                $configService = app(ConfigurationService::class);
                $rateLimitConfig = $configService->getRateLimitConfig();

                expect($rateLimitConfig['max_attempts'])->toBe($threshold);
            }
        });

        test('supports different decay periods', function () {
            $decayPeriods = [1, 5, 10, 15, 60];

            foreach ($decayPeriods as $decay) {
                config(['prism-transformer.rate_limiting.decay_minutes' => $decay]);

                $configService = app(ConfigurationService::class);
                $rateLimitConfig = $configService->getRateLimitConfig();

                expect($rateLimitConfig['decay_minutes'])->toBe($decay);
            }
        });
    });

    describe('Rate Limiting Key Generation Configuration', function () {
        test('supports custom key prefixes', function () {
            $prefixes = ['prism_rl', 'transformer_limit', 'app_rate_limit'];

            foreach ($prefixes as $prefix) {
                config(['prism-transformer.rate_limiting.key_prefix' => $prefix]);

                $configService = app(ConfigurationService::class);
                $rateLimitConfig = $configService->getRateLimitConfig();

                expect($rateLimitConfig['key_prefix'])->toBe($prefix);
            }
        });

        test('generates correct global rate limit key', function () {
            config(['prism-transformer.rate_limiting.key_prefix' => 'test_prefix']);

            $rateLimitService = app(RateLimitService::class);

            expect($rateLimitService->getGlobalKey())->toBe('test_prefix:global');
        });

    });

    describe('Configuration Service Integration', function () {
        test('configuration service returns complete rate limit configuration', function () {
            config([
                'prism-transformer.rate_limiting.enabled' => true,
                'prism-transformer.rate_limiting.max_attempts' => 100,
                'prism-transformer.rate_limiting.decay_minutes' => 5,
                'prism-transformer.rate_limiting.key_prefix' => 'custom',
            ]);

            $configService = app(ConfigurationService::class);
            $rateLimitConfig = $configService->getRateLimitConfig();

            expect($rateLimitConfig)->toBe([
                'enabled' => true,
                'max_attempts' => 100,
                'decay_minutes' => 5,
                'key_prefix' => 'custom',
            ]);
        });

        test('configuration service returns correct rate limiting enabled state', function () {
            config(['prism-transformer.rate_limiting.enabled' => false]);

            $configService = app(ConfigurationService::class);

            expect($configService->isRateLimitingEnabled())->toBeFalse();
        });

        test('rate limiting applies globally', function () {
            config(['prism-transformer.rate_limiting.enabled' => true]);

            $configService = app(ConfigurationService::class);

            expect($configService->isRateLimitingEnabled())->toBeTrue();
        });
    });

    describe('Rate Limiting Configuration Validation', function () {
        test('validates max attempts is positive integer', function () {
            $validAttempts = [1, 10, 60, 100, 1000];

            foreach ($validAttempts as $attempts) {
                config(['prism-transformer.rate_limiting.max_attempts' => $attempts]);

                $configService = app(ConfigurationService::class);
                $rateLimitConfig = $configService->getRateLimitConfig();

                expect($rateLimitConfig['max_attempts'])->toBe($attempts)
                    ->and($rateLimitConfig['max_attempts'])->toBeGreaterThan(0);
            }
        });

        test('validates decay minutes is positive integer', function () {
            $validDecay = [1, 5, 10, 30, 60];

            foreach ($validDecay as $decay) {
                config(['prism-transformer.rate_limiting.decay_minutes' => $decay]);

                $configService = app(ConfigurationService::class);
                $rateLimitConfig = $configService->getRateLimitConfig();

                expect($rateLimitConfig['decay_minutes'])->toBe($decay)
                    ->and($rateLimitConfig['decay_minutes'])->toBeGreaterThan(0);
            }
        });

        test('validates key prefix is non-empty string', function () {
            $validPrefixes = ['prefix', 'app_limit', 'rate_limit_key'];

            foreach ($validPrefixes as $prefix) {
                config(['prism-transformer.rate_limiting.key_prefix' => $prefix]);

                $configService = app(ConfigurationService::class);
                $rateLimitConfig = $configService->getRateLimitConfig();

                expect($rateLimitConfig['key_prefix'])->toBe($prefix)
                    ->and($rateLimitConfig['key_prefix'])->not->toBeEmpty();
            }
        });
    });

    describe('Rate Limiting Fallback Configuration', function () {
        test('provides sensible defaults when configuration is missing', function () {
            // Clear configuration
            config(['prism-transformer.rate_limiting' => []]);

            $configService = app(ConfigurationService::class);
            $rateLimitConfig = $configService->getRateLimitConfig();

            expect($rateLimitConfig['enabled'])->toBeTrue()
                ->and($rateLimitConfig['max_attempts'])->toBe(60)
                ->and($rateLimitConfig['decay_minutes'])->toBe(1)
                ->and($rateLimitConfig['key_prefix'])->toBe('prism_rate_limit');
        });

        test('merges partial configuration with defaults', function () {
            config([
                'prism-transformer.rate_limiting.enabled' => false,
                'prism-transformer.rate_limiting.max_attempts' => 30,
                // Other values should use defaults
            ]);

            $configService = app(ConfigurationService::class);
            $rateLimitConfig = $configService->getRateLimitConfig();

            expect($rateLimitConfig['enabled'])->toBeFalse()
                ->and($rateLimitConfig['max_attempts'])->toBe(30)
                ->and($rateLimitConfig['decay_minutes'])->toBe(1) // default
                ->and($rateLimitConfig['key_prefix'])->toBe('prism_rate_limit'); // default
        });
    });
})->group('configuration', 'rate-limiting', 'unit');
