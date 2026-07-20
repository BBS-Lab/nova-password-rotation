# Nova Password Rotation

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bbs-lab/nova-password-rotation.svg?style=flat-square)](https://packagist.org/packages/bbs-lab/nova-password-rotation)
[![Tests](https://img.shields.io/github/actions/workflow/status/BBS-Lab/nova-password-rotation/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/BBS-Lab/nova-password-rotation/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/bbs-lab/nova-password-rotation.svg?style=flat-square)](https://packagist.org/packages/bbs-lab/nova-password-rotation)

Force any authenticatable model to **rotate its password every N days**. When the password expires, a
[Laravel Nova](https://nova.laravel.com) middleware redirects the logged-in user to a native,
Nova-styled change-password screen. Light, secure, and works almost out of the box.

The rotatable subject is **not** tied to the `User` model: everything keys off the
`MustRotatePassword` interface on whatever user Nova authenticated, and the password history is
**polymorphic**, so any model works.

## Features

- 🔒 Forces a password change once the current one is older than `days`
- 🧭 Nova middleware redirect to a **native-looking** change screen, styled with Nova's own CSS
- 🧬 **Interface-gated**: only enforced when the authenticated user implements `MustRotatePassword`
- 🗂️ **Polymorphic** password history — any authenticatable model is supported
- 🚫 **Reuse prevention**: rejects the last N hashed passwords, and forbids reusing the current one
- 👋 **First-login enforcement**: admin-provisioned accounts must set their own password
- ⏰ **Expiry warning**: a Nova notification a few days before expiry (once per day, best-effort)
- 📊 `password-rotation:report` Artisan command to audit account status
- 📣 A `PasswordRotated` event you can listen to
- 🌍 English & French translations, publishable
- 🧪 100% line coverage, PHPStan level 8, no `final` classes, strict types everywhere

## Requirements

- PHP `^8.4`
- Laravel Nova `^4.0 || ^5.0`
- Laravel `^11.0 || ^12.0 || ^13.0`

Both Nova majors are exercised in CI. Note that **Nova 4** (through its `inertiajs/inertia-laravel`
dependency) tops out at **PHP 8.4** and **Laravel 11**; on PHP 8.5 or Laravel 12+, use Nova 5. Composer
resolves the right combination for you.

## Installation

Because Nova is a paid, private package, make sure your application is already authenticated against
`nova.laravel.com`, then:

```bash
composer require bbs-lab/nova-password-rotation
```

The service provider auto-registers via Laravel package discovery. The `password_histories` migration
runs automatically. Publish the config and translations if you want to tweak them:

```bash
# Config
php artisan vendor:publish --tag=nova-password-rotation-config

# Translations (en, fr)
php artisan vendor:publish --tag=nova-password-rotation-translations

# The users-table migration stub (adds the rotation column) — edit it per table before migrating
php artisan vendor:publish --tag=nova-password-rotation-user-migration

php artisan migrate
```

The published users migration adds the nullable rotation column (`password_changed_at` by default)
and **backfills existing rows to `now()`** so no one is locked out on deploy. Edit the stub to target
the correct table if your rotatable model is not `users`.

## Quick start

Add the interface and trait to the authenticatable model you want to rotate:

```php
use BBSLab\NovaPasswordRotation\Concerns\RotatesPassword;
use BBSLab\NovaPasswordRotation\Contracts\MustRotatePassword;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustRotatePassword
{
    use RotatesPassword;
}
```

The trait implements the whole interface, casts the rotation column to a `datetime`, stamps it on
every password change, records history, and fires `PasswordRotated`. On create it stamps the column
too — unless `force_on_first_login` is on (the default), in which case the column is left `null` so
the account is forced to set its own password on first login. Run the published migration and you are
done — expired users are redirected on their next full page load in Nova.

## Configuration

Every key lives in `config/nova-password-rotation.php` and is driven by an environment variable.

| Config key                 | Env var                              | Default                | Description                                                                                                    |
| -------------------------- | ------------------------------------ | ---------------------- | -------------------------------------------------------------------------------------------------------------- |
| `enabled`                  | `PASSWORD_ROTATION_ENABLED`          | `true`                 | Master switch. When `false` the package is inert: no forced change, no warnings.                               |
| `auto_register_middleware` | `PASSWORD_ROTATION_AUTO_MIDDLEWARE`  | `true`                 | Append the middleware to `config('nova.middleware')` automatically. Disable to register it yourself.           |
| `days`                     | `PASSWORD_ROTATION_DAYS`             | `90`                   | How many days a password stays valid, counted from the rotation column.                                        |
| `column`                   | `PASSWORD_ROTATION_COLUMN`           | `password_changed_at`  | The timestamp column storing when the password last changed. Override per model via `passwordRotationColumn()`.|
| `force_on_first_login`     | `PASSWORD_ROTATION_FORCE_FIRST_LOGIN`| `true`                 | Treat a `null` timestamp as expired, so provisioned accounts must set their own password.                      |
| `require_current_password` | `PASSWORD_ROTATION_REQUIRE_CURRENT`  | `true`                 | Require confirmation of the current password on the change screen.                                             |
| `history_count`            | `PASSWORD_ROTATION_HISTORY_COUNT`    | `3`                    | Number of previous (hashed) passwords remembered and rejected. `0` disables history entirely.                  |
| `warn_days`                | `PASSWORD_ROTATION_WARN_DAYS`        | `7`                    | Show a Nova notice this many days before expiry. `0` disables the warning.                                     |
| `route_prefix`             | `PASSWORD_ROTATION_ROUTE_PREFIX`     | `password-rotation`    | The change screen lives at `{nova}/{prefix}/expired`. Change only on a route collision.                        |
| `models`                   | —                                    | `['App\Models\User']`  | Models scanned by `password-rotation:report`. The middleware does **not** use this list.                       |

> The `models` array only feeds the report command. The middleware works off the interface on the
> authenticated user, so any model is enforced regardless of this list.

## Usage

### The forced-change flow

On every Nova page load, `EnsurePasswordIsNotExpired` checks the authenticated user. If it implements
`MustRotatePassword` and its password has expired, the user is redirected to the change screen. Nova 5's
auth pages are Vue/Inertia rather than Blade, so the screen is a self-contained Blade page styled with
Nova's own CSS (loaded when Nova's assets are published) — it looks native without a JS build. On a
successful update the user is sent back to the Nova dashboard with a success flash.

Because the middleware runs on Nova's **web** stack only, a mid-session expiry takes effect on the
next full page load — which is acceptable and avoids breaking XHR requests.

### Reuse prevention

When `history_count > 0`, every password change is hashed and stored in the polymorphic
`password_histories` table (only the newest N rows are kept per user). The change screen rejects any of
those previous passwords, and always rejects reusing the current one. You can reuse the rule directly:

```php
use BBSLab\NovaPasswordRotation\Rules\PasswordNotReused;

$request->validate([
    'password' => ['required', 'confirmed', new PasswordNotReused($user)],
]);
```

### First login

With `force_on_first_login` enabled (the default), a `null` rotation column counts as expired.
Admin-provisioned accounts are therefore forced to set their own password before using Nova. The
published migration backfills existing rows so current users are not caught out.

### Expiry warning

When `warn_days > 0`, a still-valid user whose password is within the warning window receives a Nova
warning notification, at most once per day. It is best-effort: if the `nova_notifications` table is not
installed, it is silently skipped and Nova keeps working.

### The `password-rotation:report` command

Audit the accounts declared in `config('nova-password-rotation.models')`:

```bash
# Only accounts that are expired or expiring within warn_days
php artisan password-rotation:report

# Every account
php artisan password-rotation:report --all
```

It renders a table of identifier, last-changed, expires-at and status (`expired` / `expiring soon` /
`ok`). Classes that do not exist or do not implement `MustRotatePassword` are skipped.

### The `PasswordRotated` event

Dispatched after a password change is persisted (not on the initial create). Listen for it to log,
notify, or revoke sessions:

```php
use BBSLab\NovaPasswordRotation\Events\PasswordRotated;
use Illuminate\Support\Facades\Event;

Event::listen(function (PasswordRotated $event) {
    logger()->info('Password rotated', ['user' => $event->authenticatable->getKey()]);
});
```

### Automatic middleware registration

By default the package wires `EnsurePasswordIsNotExpired` into Nova for you. It does so two ways so it
works on both Nova majors and even when `config/nova.php` has not been published: it appends the
middleware to `config('nova.middleware')` (Nova 4 reads this inline, Nova 5 builds its `nova` router
group from it), and — once the app has booted — it also pushes the middleware onto Nova 5's `nova`
router group. Both are gated by `enabled` and `auto_register_middleware`, and neither ever registers
the middleware twice.

### Manual middleware registration

If you set `auto_register_middleware` to `false`, add the middleware to Nova's stack yourself in
`config/nova.php` — it works on both Nova 4 and Nova 5:

```php
use BBSLab\NovaPasswordRotation\Http\Middleware\EnsurePasswordIsNotExpired;

'middleware' => [
    // ...Nova's default web middleware...
    EnsurePasswordIsNotExpired::class,
],
```

## Testing

```bash
composer test            # Pest suite
composer test-coverage   # 100% line coverage on src/
composer analyse         # PHPStan level 8
composer format          # Pint (laravel preset + strict types)
```

A full embedded Nova app (via [Orchestra Workbench](https://github.com/orchestral/workbench)) lets you
exercise the flow in a real Nova instance:

```bash
composer serve   # boots Nova at http://localhost:8000/nova
```

## Security

Passwords are stored only as hashes — the history table keeps a **copy of the already-hashed** value,
never plaintext. Reuse checks run through `Hash::check`. `Hash::make` is idempotent against a hashed
cast, so passwords stay single-hashed. If you discover a security issue, please email
`paris@big-boss-studio.com` instead of using the issue tracker.

## Filament

A Filament twin of this package is planned.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Big Boss Studio](https://github.com/BBS-Lab)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
