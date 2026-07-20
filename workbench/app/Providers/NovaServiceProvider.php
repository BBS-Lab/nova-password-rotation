<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Dashboard;
use Laravel\Nova\Dashboards\Main;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;
use Laravel\Nova\Tool;
use Workbench\App\Nova\User;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();
    }

    protected function routes(): void
    {
        // Kept to the API shared by Nova 4 and 5 so the workbench boots on both
        // majors. withoutEmailVerificationRoutes() is Nova 5 only, so guard it.
        $registration = Nova::routes()
            ->withAuthenticationRoutes(default: true)
            ->withPasswordResetRoutes();

        if (method_exists($registration, 'withoutEmailVerificationRoutes')) {
            $registration->withoutEmailVerificationRoutes();
        }

        $registration->register();
    }

    protected function gate(): void
    {
        Gate::define('viewNova', fn ($user) => true);
    }

    /**
     * @return array<int, Dashboard>
     */
    protected function dashboards(): array
    {
        return [
            new Main,
        ];
    }

    /**
     * @return array<int, Tool>
     */
    public function tools(): array
    {
        return [];
    }

    protected function resources(): void
    {
        Nova::resources([
            User::class,
        ]);
    }
}
