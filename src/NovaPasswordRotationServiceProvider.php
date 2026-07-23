<?php

declare(strict_types=1);

namespace BBSLab\NovaPasswordRotation;

use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use BBSLab\NovaPasswordRotation\Http\Middleware\EnsurePasswordIsNotExpired;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Http\Middleware\Authenticate;
use Laravel\Nova\Http\Middleware\Authorize;
use Laravel\Nova\Http\Middleware\DispatchServingNovaEvent;
use Laravel\Nova\Notifications\NovaNotification;
use Laravel\Nova\Nova;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class NovaPasswordRotationServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('nova-password-rotation')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        // Nova 4 bakes config('nova.middleware') into its routes *inline* at
        // registration, while Nova 5 builds its "nova" router middleware group
        // from the same config. Both read it during their own boot() — i.e.
        // after every register() — so appending here reaches both majors. We
        // only ever extend an already-populated array, so we never clobber (or,
        // when the config is absent, wipe) Nova's default middleware stack.
        if (! $this->autoRegistersMiddleware()) {
            return;
        }

        $middleware = config('nova.middleware');

        if (! is_array($middleware) || $middleware === []) {
            return;
        }

        if (! in_array(EnsurePasswordIsNotExpired::class, $middleware, true)) {
            $middleware[] = EnsurePasswordIsNotExpired::class;

            config(['nova.middleware' => $middleware]);
        }
    }

    public function packageBooted(): void
    {
        Route::group([
            'prefix' => trim(Nova::path(), '/').'/'.config('nova-password-rotation.route_prefix'),
            // DispatchServingNovaEvent fires the ServingNova event that configures
            // Nova's authorization, so Authorize resolves the viewNova gate instead
            // of falling back to an environment check and 403-ing every request.
            'middleware' => ['web', DispatchServingNovaEvent::class, Authenticate::class, Authorize::class],
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });

        // Nova 5 dispatches its routes through the "nova" middleware group, which
        // it (re)builds during boot from config. Pushing after the whole app has
        // booted therefore covers setups where config('nova.middleware') was not
        // published (so packageRegistered() found nothing to extend) — the
        // package still works out of the box. Harmless on Nova 4, whose routes
        // use the inline config handled above; pushMiddlewareToGroup() also
        // de-duplicates, so this never registers the middleware twice.
        $this->app->booted(function (): void {
            $this->registerGroupMiddleware();
        });

        Nova::serving(function (ServingNova $event): void {
            $this->warnBeforeExpiry($event);
        });
    }

    /**
     * Append the middleware to Nova's "nova" router group (used by Nova 5).
     */
    public function registerGroupMiddleware(): void
    {
        if (! $this->autoRegistersMiddleware()) {
            return;
        }

        Route::pushMiddlewareToGroup('nova', EnsurePasswordIsNotExpired::class);
    }

    /**
     * Whether the package should wire its middleware into Nova automatically.
     */
    private function autoRegistersMiddleware(): bool
    {
        return (bool) config('laravel-password-rotation.enabled')
            && (bool) config('nova-password-rotation.auto_register_middleware');
    }

    /**
     * Nudge a still-valid user whose password is about to expire, at most once
     * per day, best-effort (a missing notifications table must never break Nova).
     */
    private function warnBeforeExpiry(ServingNova $event): void
    {
        if (! config('laravel-password-rotation.enabled')) {
            return;
        }

        $warnDays = (int) config('laravel-password-rotation.warn_days');

        if ($warnDays <= 0) {
            return;
        }

        $user = Nova::user($event->request);

        if (! $user instanceof MustRotatePassword || $user->passwordHasExpired()) {
            return;
        }

        $expiresAt = $user->passwordExpiresAt();

        if ($expiresAt === null || now()->lt($expiresAt->copy()->subDays($warnDays))) {
            return;
        }

        if (! method_exists($user, 'notify')) {
            return;
        }

        // Key on the class too: distinct rotatable models can share a primary
        // key value, and each must get its own warning (mirrors PasswordHistory).
        $key = 'nova-password-rotation:warned:'.$user::class.':'.((string) $user->getKey()).':'.now()->format('Y-m-d');

        if (Cache::get($key) !== null) {
            return;
        }

        Cache::put($key, true, now()->endOfDay());

        try {
            $user->notify(
                NovaNotification::make()
                    ->type(NovaNotification::WARNING_TYPE)
                    ->icon('exclamation-circle')
                    ->message((string) trans('nova-password-rotation::messages.warning', [
                        'date' => $expiresAt->toFormattedDateString(),
                    ]))
            );
        } catch (\Throwable) {
            // Best-effort only: Nova notifications may not be installed.
        }
    }
}
