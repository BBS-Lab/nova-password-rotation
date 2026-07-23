<?php

declare(strict_types=1);

use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Notifications\NovaNotification;
use Workbench\Database\Factories\AdminFactory;
use Workbench\Database\Factories\UserFactory;

uses(RefreshDatabase::class);

beforeEach(fn () => config([
    'laravel-password-rotation.enabled' => true,
    'laravel-password-rotation.days' => 90,
    'laravel-password-rotation.warn_days' => 7,
    'laravel-password-rotation.force_on_first_login' => true,
]));

function serve(mixed $user): void
{
    $request = Request::create('/nova');
    $request->setUserResolver(fn () => $user);

    // Nova 5's ServingNova constructor takes ($app, $request); Nova 4's takes
    // just ($request). Build it to match whichever major is installed.
    $constructor = (new ReflectionClass(ServingNova::class))->getConstructor();
    $event = $constructor !== null && $constructor->getNumberOfParameters() === 1
        ? new ServingNova($request)
        : new ServingNova(app(), $request);

    event($event);
}

function warnedKey(mixed $user): string
{
    return 'nova-password-rotation:warned:'.$user::class.':'.$user->getKey().':'.now()->format('Y-m-d');
}

it('warns a user whose password is about to expire', function (): void {
    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(85)]);

    serve($user);

    // The dedupe key is written just before the (best-effort) notification.
    expect(Cache::get(warnedKey($user)))->toBeTrue();
});

it('sends a Nova warning notification carrying the expiry date', function (): void {
    $this->freezeTime();
    Notification::fake();

    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(85)]);
    $expiresAt = now()->subDays(85)->addDays(90);

    serve($user);

    Notification::assertSentTo(
        $user,
        NovaNotification::class,
        fn (NovaNotification $notification): bool => $notification->type === NovaNotification::WARNING_TYPE
            && $notification->message === (string) trans('nova-password-rotation::messages.warning', [
                'date' => $expiresAt->toFormattedDateString(),
            ]),
    );
});

it('warns exactly one day before expiry when the warning window is one day', function (): void {
    $this->freezeTime();
    config(['laravel-password-rotation.warn_days' => 1]);

    // days = 90, so this account expires in exactly one day: on the boundary.
    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(89)]);

    serve($user);

    expect(Cache::get(warnedKey($user)))->toBeTrue();
});

it('warns at most once per user per day', function (): void {
    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(85)]);

    Cache::put(warnedKey($user), true, now()->endOfDay());

    serve($user); // must return early, no exception

    expect(Cache::get(warnedKey($user)))->toBeTrue();
});

it('does not warn outside the warning window', function (): void {
    $user = UserFactory::new()->create(['password_changed_at' => now()]);

    serve($user);

    expect(Cache::get(warnedKey($user)))->toBeNull();
});

it('does not warn when the feature is disabled', function (): void {
    config(['laravel-password-rotation.enabled' => false]);

    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(85)]);

    serve($user);

    expect(Cache::get(warnedKey($user)))->toBeNull();
});

it('does not warn when the warning window is disabled', function (): void {
    config(['laravel-password-rotation.warn_days' => 0]);

    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(85)]);

    serve($user);

    expect(Cache::get(warnedKey($user)))->toBeNull();
});

it('does not warn at the exact expiry moment when the warning window is disabled', function (): void {
    $this->freezeTime();
    config(['laravel-password-rotation.warn_days' => 0]);

    // Expires exactly now (days = 90): still not "past", so passwordHasExpired()
    // lets us through, and the L139 window check (now < expiry) cannot fire
    // either. Only the `warn_days <= 0` guard can suppress the warning here, so
    // this pins its boundary — a <, a -1 or a dropped early return would warn.
    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(90)]);

    serve($user);

    expect(Cache::get(warnedKey($user)))->toBeNull();
});

it('does not warn an already expired user', function (): void {
    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(120)]);

    serve($user);

    expect(Cache::get(warnedKey($user)))->toBeNull();
});

it('does not warn a user that does not implement the interface', function (): void {
    $admin = AdminFactory::new()->create();

    serve($admin);

    expect(Cache::get(warnedKey($admin)))->toBeNull();
});

it('does not warn when expiry cannot be determined', function (): void {
    config(['laravel-password-rotation.force_on_first_login' => false]);

    // Null timestamp + force_on_first_login off: not expired, yet no expiry date.
    $user = UserFactory::new()->create();
    $user->forceFill(['password_changed_at' => null])->saveQuietly();
    $user->refresh();

    serve($user);

    expect(Cache::get(warnedKey($user)))->toBeNull();
});

it('swallows notification failures (best-effort delivery)', function (): void {
    // In-window, notifiable, but notify() blows up (e.g. missing table).
    $user = new class implements MustRotatePassword
    {
        public function getKey(): int
        {
            return 4242;
        }

        public function passwordRotationColumn(): string
        {
            return 'password_changed_at';
        }

        public function passwordLastChangedAt(): ?CarbonInterface
        {
            return now()->subDays(85);
        }

        public function passwordExpiresAt(): ?CarbonInterface
        {
            return now()->addDays(5);
        }

        public function passwordHasExpired(): bool
        {
            return false;
        }

        public function passwordIsExpiring(): bool
        {
            return true;
        }

        public function notify(mixed $notification): never
        {
            throw new RuntimeException('notifications unavailable');
        }
    };

    serve($user); // must not bubble the exception up

    // The dedupe key is written before the doomed notification attempt.
    expect(Cache::get(warnedKey($user)))->toBeTrue();
});

it('does not warn a rotatable user that cannot be notified', function (): void {
    // A rotatable subject inside the warning window but without a notify() method.
    $user = new class implements MustRotatePassword
    {
        public function getKey(): int
        {
            return 999;
        }

        public function passwordRotationColumn(): string
        {
            return 'password_changed_at';
        }

        public function passwordLastChangedAt(): ?CarbonInterface
        {
            return now()->subDays(85);
        }

        public function passwordExpiresAt(): ?CarbonInterface
        {
            return now()->addDays(5);
        }

        public function passwordHasExpired(): bool
        {
            return false;
        }

        public function passwordIsExpiring(): bool
        {
            return true;
        }
    };

    serve($user);

    expect(Cache::get(warnedKey($user)))->toBeNull();
});
