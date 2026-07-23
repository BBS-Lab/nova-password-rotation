<?php

declare(strict_types=1);

use BBSLab\NovaPasswordRotation\Http\Middleware\EnsurePasswordIsNotExpired;
use BBSLab\NovaPasswordRotation\NovaPasswordRotationServiceProvider;
use Illuminate\Support\Facades\Route;
use Laravel\Nova\Http\Middleware\Authenticate;
use Laravel\Nova\Http\Middleware\Authorize;
use Laravel\Nova\Http\Middleware\DispatchServingNovaEvent;

function reRegister(): void
{
    /** @var NovaPasswordRotationServiceProvider $provider */
    $provider = app()->getProvider(NovaPasswordRotationServiceProvider::class);
    $provider->packageRegistered();
}

function pushToNovaGroup(): void
{
    /** @var NovaPasswordRotationServiceProvider $provider */
    $provider = app()->getProvider(NovaPasswordRotationServiceProvider::class);
    $provider->registerGroupMiddleware();
}

function novaGroup(): array
{
    return app('router')->getMiddlewareGroups()['nova'] ?? [];
}

function stripFromNovaGroup(): void
{
    Route::middlewareGroup('nova', array_values(array_diff(novaGroup(), [EnsurePasswordIsNotExpired::class])));
}

it('appends its middleware to Nova when auto-registration is enabled', function (): void {
    config([
        'laravel-password-rotation.enabled' => true,
        'nova-password-rotation.auto_register_middleware' => true,
        'nova.middleware' => ['web'],
    ]);

    reRegister();

    expect(config('nova.middleware'))->toContain(EnsurePasswordIsNotExpired::class);
});

it('does not register the middleware twice', function (): void {
    config([
        'laravel-password-rotation.enabled' => true,
        'nova-password-rotation.auto_register_middleware' => true,
        'nova.middleware' => ['web', EnsurePasswordIsNotExpired::class],
    ]);

    reRegister();

    $count = count(array_keys(config('nova.middleware'), EnsurePasswordIsNotExpired::class, true));

    expect($count)->toBe(1);
});

it('does not register the middleware when the feature is disabled', function (): void {
    config([
        'laravel-password-rotation.enabled' => false,
        'nova-password-rotation.auto_register_middleware' => true,
        'nova.middleware' => ['web'],
    ]);

    reRegister();

    expect(config('nova.middleware'))->not->toContain(EnsurePasswordIsNotExpired::class);
});

it('does not register the middleware when auto-registration is turned off', function (): void {
    config([
        'laravel-password-rotation.enabled' => true,
        'nova-password-rotation.auto_register_middleware' => false,
        'nova.middleware' => ['web'],
    ]);

    reRegister();

    expect(config('nova.middleware'))->not->toContain(EnsurePasswordIsNotExpired::class);
});

it('leaves nova.middleware untouched when it is not a populated array', function (): void {
    config([
        'laravel-password-rotation.enabled' => true,
        'nova-password-rotation.auto_register_middleware' => true,
        'nova.middleware' => null,
    ]);

    reRegister();

    expect(config('nova.middleware'))->toBeNull();

    config(['nova.middleware' => []]);

    reRegister();

    expect(config('nova.middleware'))->toBe([]);
});

it('pushes the middleware into the Nova router group when enabled', function (): void {
    config([
        'laravel-password-rotation.enabled' => true,
        'nova-password-rotation.auto_register_middleware' => true,
    ]);

    stripFromNovaGroup();
    pushToNovaGroup();

    expect(novaGroup())->toContain(EnsurePasswordIsNotExpired::class);
});

it('wires its middleware into the Nova router group at boot', function (): void {
    // No manual push: the middleware must already be in the group from the
    // packageBooted() -> app->booted() -> registerGroupMiddleware() chain that
    // ran during the harness boot. Dropping either call leaves the group bare.
    expect(novaGroup())->toContain(EnsurePasswordIsNotExpired::class);
});

it('does not push into the Nova router group when auto-registration is off', function (): void {
    config([
        'laravel-password-rotation.enabled' => true,
        'nova-password-rotation.auto_register_middleware' => false,
    ]);

    stripFromNovaGroup();
    pushToNovaGroup();

    expect(novaGroup())->not->toContain(EnsurePasswordIsNotExpired::class);
});

it('registers the change screen under the Nova path with the configured prefix', function (): void {
    $route = app('router')->getRoutes()->getByName('nova-password-rotation.expired.show');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('nova/password-rotation/expired');
});

it('guards the change screen with Nova authorization middleware', function (): void {
    $middleware = app('router')->getRoutes()
        ->getByName('nova-password-rotation.expired.show')
        ->gatherMiddleware();

    expect($middleware)
        ->toContain('web')
        ->toContain(DispatchServingNovaEvent::class)
        ->toContain(Authenticate::class)
        ->toContain(Authorize::class);
});
