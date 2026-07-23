<?php

declare(strict_types=1);

use BBSLab\LaravelPasswordRotation\Events\PasswordRotated;
use BBSLab\LaravelPasswordRotation\Models\PasswordHistory;
use BBSLab\NovaPasswordRotation\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Laravel\Nova\Nova;
use Workbench\App\Models\User;
use Workbench\Database\Factories\AdminFactory;
use Workbench\Database\Factories\UserFactory;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

    // The route group is gated by Nova's Authorize middleware; a real app wires
    // this through its NovaServiceProvider (viewNova gate). Nova's providers are
    // not booted in this isolated package harness, so configure it here.
    Nova::auth(fn () => true);
});

function expiredRotatable(): User
{
    return UserFactory::new()->create(['password_changed_at' => now()->subDays(100)]);
}

/** Reload a user so Nova::user is not a "recently created" in-memory instance. */
function reload(User $user): User
{
    return User::query()->findOrFail($user->getKey());
}

describe('show', function (): void {
    it('renders the change screen for an expired rotatable user', function (): void {
        $this->actingAs(reload(expiredRotatable()))
            ->get(route('nova-password-rotation.expired.show'))
            ->assertOk()
            ->assertSee(trans('nova-password-rotation::messages.title'));
    });

    it('shows the change-password form (with a password field) in change mode', function (): void {
        config(['nova-password-rotation.expiry_action' => 'change']);

        $this->actingAs(reload(expiredRotatable()))
            ->get(route('nova-password-rotation.expired.show'))
            ->assertOk()
            ->assertSee(trans('nova-password-rotation::messages.new_password'))
            ->assertDontSee(trans('nova-password-rotation::messages.reset_submit'));
    });

    it('shows a reset-link card with no password fields in reset mode', function (): void {
        config(['nova-password-rotation.expiry_action' => 'reset']);

        $this->actingAs(reload(expiredRotatable()))
            ->get(route('nova-password-rotation.expired.show'))
            ->assertOk()
            ->assertSee(trans('nova-password-rotation::messages.reset_intro'))
            ->assertSee(trans('nova-password-rotation::messages.reset_submit'))
            ->assertDontSee(trans('nova-password-rotation::messages.new_password'));
    });

    it('redirects away when the password is still valid', function (): void {
        $this->actingAs(UserFactory::new()->create(['password_changed_at' => now()]))
            ->get(route('nova-password-rotation.expired.show'))
            ->assertRedirect(Nova::path());
    });

    it('redirects a user that does not implement the interface', function (): void {
        $this->actingAs(AdminFactory::new()->create())
            ->get(route('nova-password-rotation.expired.show'))
            ->assertRedirect(Nova::path());
    });
});

describe('update', function (): void {
    it('rotates the password, stamps, records history and fires the event', function (): void {
        Event::fake([PasswordRotated::class]);

        $user = reload(expiredRotatable());
        $historyBefore = PasswordHistory::query()->count();

        $this->actingAs($user)
            ->post(route('nova-password-rotation.expired.update'), [
                'current_password' => 'password',
                'password' => 'Sup3r-Str0ng-Pass!',
                'password_confirmation' => 'Sup3r-Str0ng-Pass!',
            ])
            ->assertRedirect(Nova::path())
            ->assertSessionHas('status');

        $after = User::query()->findOrFail($user->getKey());

        expect(Hash::check('Sup3r-Str0ng-Pass!', $after->password))->toBeTrue()
            ->and($after->password_changed_at->isToday())->toBeTrue()
            ->and(PasswordHistory::query()->count())->toBe($historyBefore + 1);

        Event::assertDispatched(PasswordRotated::class);
    });

    it('rejects a weak password', function (): void {
        $this->actingAs(reload(expiredRotatable()))
            ->post(route('nova-password-rotation.expired.update'), [
                'current_password' => 'password',
                'password' => 'weak',
                'password_confirmation' => 'weak',
            ])
            ->assertSessionHasErrors('password');
    });

    it('rejects a mismatched confirmation', function (): void {
        $this->actingAs(reload(expiredRotatable()))
            ->post(route('nova-password-rotation.expired.update'), [
                'current_password' => 'password',
                'password' => 'Sup3r-Str0ng-Pass!',
                'password_confirmation' => 'something-else',
            ])
            ->assertSessionHasErrors('password');
    });

    it('rejects a missing password (closure tolerates a non-string value)', function (): void {
        $this->actingAs(reload(expiredRotatable()))
            ->post(route('nova-password-rotation.expired.update'), [
                'current_password' => 'password',
            ])
            ->assertSessionHasErrors('password');
    });

    it('rejects a new password equal to the current one', function (): void {
        $this->actingAs(reload(expiredRotatable()))
            ->post(route('nova-password-rotation.expired.update'), [
                'current_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrors('password');
    });

    it('rejects reusing the current password even with history disabled', function (): void {
        // History off, so the "different from current" closure is the only guard
        // catching a password equal to the current one.
        config([
            'laravel-password-rotation.history_count' => 0,
            'laravel-password-rotation.require_current_password' => false,
        ]);

        $user = reload(expiredRotatable());
        $user->password = 'Str0ng-Curr3nt-Pass!'; // a strong current password
        $user->save();

        $this->actingAs(reload($user))
            ->post(route('nova-password-rotation.expired.update'), [
                'password' => 'Str0ng-Curr3nt-Pass!',
                'password_confirmation' => 'Str0ng-Curr3nt-Pass!',
            ])
            ->assertSessionHasErrors('password');
    });

    it('applies the reuse rule at a history window of exactly one', function (): void {
        // With history_count = 1, a genuinely-previous (non-current) password
        // must still be rejected — the rule is added only when count > 0.
        config([
            'laravel-password-rotation.history_count' => 1,
            'laravel-password-rotation.require_current_password' => false,
        ]);

        $user = expiredRotatable();

        // Rotate twice so the previous password is strong (passes the strength
        // rule) and distinct from the current one (passes the different check):
        // only the reuse rule can reject it.
        $rotated = reload($user);
        $rotated->password = 'Str0ng-Prev-P4ss!';
        $rotated->save();

        $rotated = reload($user);
        $rotated->password = 'Str0ng-Curr-P4ss!';
        $rotated->save(); // current; 'Str0ng-Prev-P4ss!' is now one change ago

        $this->actingAs(reload($user))
            ->post(route('nova-password-rotation.expired.update'), [
                'password' => 'Str0ng-Prev-P4ss!',
                'password_confirmation' => 'Str0ng-Prev-P4ss!',
            ])
            ->assertSessionHasErrors('password');
    });

    it('requires the current password field to be present when configured', function (): void {
        config(['laravel-password-rotation.require_current_password' => true]);

        // current_password omitted entirely: the 'required' rule must fire.
        $this->actingAs(reload(expiredRotatable()))
            ->post(route('nova-password-rotation.expired.update'), [
                'password' => 'Sup3r-Str0ng-Pass!',
                'password_confirmation' => 'Sup3r-Str0ng-Pass!',
            ])
            ->assertSessionHasErrors('current_password');
    });

    it('rejects the wrong current password', function (): void {
        config(['laravel-password-rotation.require_current_password' => true]);

        $this->actingAs(reload(expiredRotatable()))
            ->post(route('nova-password-rotation.expired.update'), [
                'current_password' => 'not-the-password',
                'password' => 'Sup3r-Str0ng-Pass!',
                'password_confirmation' => 'Sup3r-Str0ng-Pass!',
            ])
            ->assertSessionHasErrors('current_password');
    });

    it('rejects a previously used password and skips the current-password field', function (): void {
        config([
            'laravel-password-rotation.history_count' => 3,
            'laravel-password-rotation.require_current_password' => false,
        ]);

        $user = expiredRotatable(); // history contains 'password'
        $rotated = reload($user);
        $rotated->password = 'An0ther-Str0ng-Pass!';
        $rotated->save(); // history now: 'password' + 'An0ther-Str0ng-Pass!'

        $this->actingAs(reload($user))
            ->post(route('nova-password-rotation.expired.update'), [
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrors('password');
    });

    it('succeeds with history disabled and current-password not required', function (): void {
        config([
            'laravel-password-rotation.history_count' => 0,
            'laravel-password-rotation.require_current_password' => false,
        ]);

        $user = reload(expiredRotatable());

        $this->actingAs($user)
            ->post(route('nova-password-rotation.expired.update'), [
                'password' => 'Fresh-Str0ng-Pass!',
                'password_confirmation' => 'Fresh-Str0ng-Pass!',
            ])
            ->assertRedirect(Nova::path());

        expect(PasswordHistory::query()->count())->toBe(0);
    });

    it('redirects a non-interface user without validating', function (): void {
        $this->actingAs(AdminFactory::new()->create())
            ->post(route('nova-password-rotation.expired.update'), [])
            ->assertRedirect(Nova::path());
    });
});

describe('reset (expiry_action=reset)', function (): void {
    beforeEach(function (): void {
        config([
            'nova-password-rotation.expiry_action' => 'reset',
            // The default password broker resolves the user through this provider
            // model; point it at the rotatable workbench user so the reset link is
            // emailed to the same instance the assertions expect.
            'auth.providers.users.model' => User::class,
        ]);
    });

    it('emails a link pointing at the Nova reset page, logs the user out and redirects to login', function (): void {
        // Stand in for Nova's own reset page, which Nova registers only in a
        // fully-booted panel, not this isolated harness.
        Route::get('/nova/password/reset/{token}', fn () => 'reset')->name('nova.pages.password.reset');

        Notification::fake();

        $user = reload(expiredRotatable());

        $this->actingAs($user)
            ->post(route('nova-password-rotation.expired.reset'))
            ->assertRedirect(Nova::url('/login'))
            ->assertSessionHas('status', trans('nova-password-rotation::messages.reset_sent'));

        $this->assertGuest();

        // The link resolves to Nova's reset page — NOT the framework default
        // route('password.reset') a Nova app doesn't define (the bug this guards
        // against). Building that URL is the step that used to throw.
        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            fn (ResetPasswordNotification $notification): bool => str_contains($notification->url, '/nova/password/reset/'),
        );
    });

    it('falls back to a standard password.reset route when Nova reset is not configured', function (): void {
        Route::get('/reset-password/{token}', fn () => 'reset')->name('password.reset');

        Notification::fake();

        $user = reload(expiredRotatable());

        $this->actingAs($user)
            ->post(route('nova-password-rotation.expired.reset'))
            ->assertRedirect(Nova::url('/login'));

        $this->assertGuest();

        // The standard flow's own notification is used (its URL already resolves).
        Notification::assertSentTo($user, ResetPassword::class);
    });

    it('throws a clear error when no reset page route exists', function (): void {
        $user = reload(expiredRotatable());

        expect(fn () => $this->withoutExceptionHandling()
            ->actingAs($user)
            ->post(route('nova-password-rotation.expired.reset')))
            ->toThrow(LogicException::class);
    });

    it('redirects a user that does not implement the interface without sending', function (): void {
        Notification::fake();

        $this->actingAs(AdminFactory::new()->create())
            ->post(route('nova-password-rotation.expired.reset'))
            ->assertRedirect(Nova::path());

        Notification::assertNothingSent();
    });

    it('renders its notification mail with the explicitly assigned reset url', function (): void {
        $notification = new ResetPasswordNotification('token-123');
        $notification->url = 'https://app.test/nova/password/reset/token-123';

        expect($notification->toMail(new User)->actionUrl)
            ->toBe('https://app.test/nova/password/reset/token-123');
    });
});
