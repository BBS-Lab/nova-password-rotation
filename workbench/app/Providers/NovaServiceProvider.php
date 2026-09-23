<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use BBSLab\LaravelPasswordRotation\Facades\PasswordRotation;
use Illuminate\Http\Request;
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

        // Demo of the bypass hook: exempt SSO-provisioned accounts from the forced
        // rotation. The callback receives the request and the expired user; here it
        // reads the seeded `is_sso` flag. A real app might instead read a session
        // attribute set at SSO login: fn ($request) => $request->session()->get('sso').
        PasswordRotation::bypass(
            fn (Request $request, MustRotatePassword $user): bool => $user->is_sso === true,
        );
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
