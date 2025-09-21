<?php

namespace Droath\PrismTransformer;

use Illuminate\Cache\RateLimiter;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Services\RateLimitService;

class PrismTransformerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('prism-transformer')
            ->hasViews()
            ->hasConfigFile()
            ->hasMigration('create_prism_transformer_table');
    }

    public function packageRegistered(): void
    {
        // Bind the configuration service as a singleton
        $this->app->singleton(ConfigurationService::class, function ($app) {
            return new ConfigurationService();
        });

        // Bind the rate limit service as a singleton
        $this->app->singleton(RateLimitService::class, function ($app) {
            return new RateLimitService(
                $app->make(RateLimiter::class),
                $app->make(ConfigurationService::class),
            );
        });

        // Bind the main PrismTransformer class
        $this->app->bind(PrismTransformer::class, function ($app) {
            return new PrismTransformer(
                $app->make(RateLimitService::class),
            );
        });
    }
}
