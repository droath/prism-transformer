<?php

namespace Droath\PrismTransformer;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Droath\PrismTransformer\Commands\PrismTransformerCommand;
use Droath\PrismTransformer\Services\ConfigurationService;

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
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_prism_transformer_table')
            ->hasCommand(PrismTransformerCommand::class);
    }

    public function packageRegistered(): void
    {
        // Bind the configuration service as a singleton
        $this->app->singleton(ConfigurationService::class, function ($app) {
            return new ConfigurationService();
        });
    }
}
