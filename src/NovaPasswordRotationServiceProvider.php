<?php

declare(strict_types=1);

namespace BBSLab\NovaPasswordRotation;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class NovaPasswordRotationServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('nova-password-rotation')
            ->hasConfigFile()
            ->hasTranslations();
    }
}
