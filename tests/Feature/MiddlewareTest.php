<?php

declare(strict_types=1);

use BBSLab\NovaPasswordRotation\Contracts\MustRotatePassword;
use BBSLab\NovaPasswordRotation\Http\Middleware\EnsurePasswordIsNotExpired;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;
use Workbench\Database\Factories\AdminFactory;
use Workbench\Database\Factories\UserFactory;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    Route::middleware(['web', EnsurePasswordIsNotExpired::class])->group(function (): void {
        Route::get('/nova/panel', fn () => 'panel');
        Route::get('/nova/logout', fn () => 'bye')->name('nova.logout');
        Route::get('/nova/password-rotation/probe', fn () => 'ours')->name('nova-password-rotation.probe');
    });
});

function expiredUser(): User
{
    return UserFactory::new()->create(['password_changed_at' => now()->subDays(100)]);
}

it('redirects an expired user to the change screen', function (): void {
    $this->actingAs(expiredUser())
        ->get('/nova/panel')
        ->assertRedirect(route('nova-password-rotation.expired.show'));
});

it('lets a still-valid user through', function (): void {
    $this->actingAs(UserFactory::new()->create(['password_changed_at' => now()]))
        ->get('/nova/panel')
        ->assertOk()
        ->assertSee('panel');
});

it('ignores users that do not implement the interface', function (): void {
    $this->actingAs(AdminFactory::new()->create())
        ->get('/nova/panel')
        ->assertOk()
        ->assertSee('panel');
});

it('is inert when the feature is disabled', function (): void {
    config(['nova-password-rotation.enabled' => false]);

    $this->actingAs(expiredUser())
        ->get('/nova/panel')
        ->assertOk()
        ->assertSee('panel');
});

it('lets a self-declared expired user through when the feature is disabled', function (): void {
    config(['nova-password-rotation.enabled' => false]);

    // A user that reports itself expired regardless of config; only the
    // middleware's own "disabled" short-circuit can let this request through.
    $user = new class extends User implements MustRotatePassword
    {
        public function passwordHasExpired(): bool
        {
            return true;
        }
    };

    $this->actingAs($user)
        ->get('/nova/panel')
        ->assertOk()
        ->assertSee('panel');
});

it('never traps the user on one of its own routes', function (): void {
    $this->actingAs(expiredUser())
        ->get('/nova/password-rotation/probe')
        ->assertOk()
        ->assertSee('ours');
});

it('never traps the user on the way out (logout)', function (): void {
    $this->actingAs(expiredUser())
        ->get('/nova/logout')
        ->assertOk()
        ->assertSee('bye');
});
