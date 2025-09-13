<?php

namespace Droath\PrismTransformer;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Droath\PrismTransformer\Commands\PrismTransformerCommand;

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
}
