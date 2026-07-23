<?php

declare(strict_types=1);

namespace BBSLab\NovaPasswordRotation\Tests;

use BBSLab\LaravelPasswordRotation\LaravelPasswordRotationServiceProvider;
use BBSLab\NovaPasswordRotation\NovaPasswordRotationServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use WithWorkbench;

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        // A consuming app auto-discovers the base package; the testbench harness
        // does not, so register it explicitly (it owns the shared config, the
        // password_histories migration and the report command).
        return [
            LaravelPasswordRotationServiceProvider::class,
            NovaPasswordRotationServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // Pin the package default so the suite tests 'change' mode deterministically,
        // immune to the workbench .env (whose PASSWORD_ROTATION_EXPIRY_ACTION may be
        // flipped to 'reset' for the `composer serve` demo). Reset-mode tests opt in
        // explicitly with config(['nova-password-rotation.expiry_action' => 'reset']).
        $app['config']->set('nova-password-rotation.expiry_action', 'change');
    }
}
