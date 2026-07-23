# Changelog

All notable changes to `bbs-lab/nova-password-rotation` will be documented in this file.

## v1.0.2 - 2026-07-23

Compatibility & maintenance release — wider PHP support and CI coverage. Fully backward compatible.

### 🔧 Changed

- **Minimum PHP lowered to `^8.2`** (from `^8.4`). The rotation domain runs on PHP 8.2+, so the package no longer forces 8.4 — install it on any supported Laravel (11/12/13) running PHP 8.2 or newer. (Nova 4 still tops out at PHP 8.4 / Laravel 11 through its Inertia dependency; Composer resolves the right combination for you.)

### 👷 CI

- Broadened the test matrix to **Nova 4 & 5 × Laravel 11/12/13 × PHP 8.3/8.4/8.5**. The runtime floor is PHP 8.2, but Pest 4 (dev) requires 8.3, so 8.3 is the lowest PHP the CI can exercise.
- Bumped `actions/checkout` to v7.

### 📚 Documentation

- The README now cross-references the (now released) Filament twin, [`bbs-lab/filament-password-rotation`](https://github.com/BBS-Lab/filament-password-rotation).

## v1.0.1 - 2026-07-22

Maintenance release — configurable morph key type for the password history, plus documentation polish.

### ✨ Added

- **Configurable morph key type** — new `morph_key_type` config option (env `PASSWORD_ROTATION_MORPH_KEY_TYPE`) so the polymorphic `password_histories.authenticatable_id` column can be provisioned as `uuid` or `ulid` for string-keyed authenticatables instead of the default BIGINT. Leave it `null` to follow Laravel's `Schema::defaultMorphKeyType()`.

### 🔧 Changed

- `config('nova-password-rotation.column')` now falls back to `password_changed_at` in both the `RotatesPassword` trait and the publishable user-migration stub, so a missing config key never yields an empty column name.

### 📚 Documentation

- Added light/dark showcase screenshots (forced-change screen, expiry notification, sign-in) to the README.
- Corrected the `LICENSE` copyright holder (was a leftover skeleton placeholder).

## v1.0.0 — Initial release - 2026-07-20

First stable release of **Nova Password Rotation** — force any authenticatable implementing `MustRotatePassword` to rotate its password every N days. When the password expires, a [Laravel Nova](https://nova.laravel.com) middleware redirects the logged-in user to a native, Nova-styled change-password screen. Light, secure, and works almost out of the box on **Nova 4 and Nova 5**.

### ✨ Features

- **Interface-driven** — `MustRotatePassword` interface + `RotatesPassword` trait on any authenticatable (not tied to `User`); auto-stamps the timestamp on every password change
- **Forced rotation** — configurable period (`days`); expired users are redirected to a native, Nova-styled change screen
- **Auto-registered middleware** — `EnsurePasswordIsNotExpired` wired into Nova for you, robust across Nova 4 (inline `config('nova.middleware')`) and Nova 5 (the `nova` router group), gated by config and de-duplicated
- **Reuse prevention** — polymorphic password history rejects the last N passwords (reusable `PasswordNotReused` rule)
- **First-login enforcement** — a `null` timestamp counts as expired, so admin-provisioned accounts must set their own password
- **Expiry warning** — best-effort Nova notification within a configurable window, at most once per day
- **Tooling** — `password-rotation:report` Artisan command and a `PasswordRotated` event
- **i18n & config** — English & French translations, publishable; every setting is environment-driven

### ✅ Quality

- **100% line coverage**, mutation tested (**MSI 89%**), PHPStan level 8, Pint
- Verified in CI on **Nova 5** (Laravel 11/12/13, PHP 8.4/8.5) and **Nova 4** (Laravel 11, PHP 8.4)

### 📦 Requirements

PHP `^8.4` · Laravel Nova `^4.0 || ^5.0` · Laravel `^11.0 || ^12.0 || ^13.0`

> Nova 4 (through its Inertia dependency) tops out at PHP 8.4 and Laravel 11; on PHP 8.5 or Laravel 12+, use Nova 5.

### 🚀 Installation

```bash
composer require bbs-lab/nova-password-rotation


```
```php
use BBSLab\NovaPasswordRotation\Concerns\RotatesPassword;
use BBSLab\NovaPasswordRotation\Contracts\MustRotatePassword;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustRotatePassword
{
    use RotatesPassword;
}


```