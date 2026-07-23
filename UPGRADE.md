# Upgrade guide

## From v1.x to v2.0.0

v2.0.0 extracts the generic password-rotation domain into the standalone
[`bbs-lab/laravel-password-rotation`](https://github.com/BBS-Lab/laravel-password-rotation)
package and depends on it. This package now only carries the Nova-specific
layer (middleware auto-registration, the Nova-styled forced-change screen and
the expiry warning notification). The behaviour is unchanged out of the box,
but a few things moved namespace. This is a breaking release; follow the steps
below.

`composer update` pulls in `bbs-lab/laravel-password-rotation` automatically.

### 1. Rotation config keys moved to `laravel-password-rotation.*`

The nine generic rotation keys now live in the base package's config file.
Publish it and move any overrides you had in `config/nova-password-rotation.php`:

```bash
php artisan vendor:publish --tag=laravel-password-rotation-config
```

Keys that moved from `nova-password-rotation.*` to `laravel-password-rotation.*`:

- `enabled`
- `morph_key_type`
- `days`
- `column`
- `force_on_first_login`
- `require_current_password`
- `history_count`
- `warn_days`
- `models`

The environment variable names are unchanged (`PASSWORD_ROTATION_ENABLED`,
`PASSWORD_ROTATION_DAYS`, …), so if you configured the package purely through
`.env` there is nothing to do here.

The keys that stay in `config/nova-password-rotation.php` are the Nova-specific
ones: `auto_register_middleware`, `route_prefix`, and the new `expiry_action`
(see below).

### 2. Migrations

The `password_histories` table migration is now owned and **run automatically**
by the base package — you no longer publish or run it from this package. Your
existing `password_histories` table is compatible; nothing to migrate.

The publishable "first-login" user-column migration also moved to the base
package. Its publish tag changed:

- Old: `--tag=nova-password-rotation-user-migration`
- New: `--tag=laravel-password-rotation-user-migration`

If you already published and ran that migration, no action is needed.

### 3. Overridden `validation.*` translations moved

If you published and customised the reuse/different validation messages, they
now live under the `laravel-password-rotation::` namespace
(`validation.reused` and `validation.different`). Republish and re-apply your
overrides:

```bash
php artisan vendor:publish --tag=laravel-password-rotation-translations
```

The Nova UI strings (`messages.*`: the screen title, field labels, the expiry
warning, and the new reset-card strings) stay under this package's
`nova-password-rotation::` namespace.

### 4. New option: `expiry_action`

`config/nova-password-rotation.php` gains an `expiry_action` key
(env `PASSWORD_ROTATION_EXPIRY_ACTION`), default `'change'`:

- `'change'` (default) — the existing behaviour: the forced-change screen shows
  the in-panel change-password form.
- `'reset'` — the screen shows a single "Send password reset link" button
  instead. Submitting it emails a reset link (pointing at Nova's own reset page,
  or a standard `password.reset` route if that is what your app uses), signs the
  user out of the Nova guard (so the emailed guest link is reachable) and
  redirects to the Nova login. This requires the model to implement
  `CanResetPassword` and a reset page to exist — enable Nova password reset
  (`Nova::routes()->withPasswordResetRoutes()`) or define a `password.reset`
  route.

Leaving `expiry_action` unset keeps the v1.x behaviour.

### Namespace changes for custom code

If your app references the generic classes directly, update the namespace from
`BBSLab\NovaPasswordRotation\` to `BBSLab\LaravelPasswordRotation\` for:

- `Contracts\MustRotatePassword`
- `Concerns\RotatesPassword`
- `Models\PasswordHistory`
- `Rules\PasswordNotReused`
- `Events\PasswordRotated`
- `Console\Commands\PasswordRotationReport` (the `password-rotation:report`
  command is now registered by the base package)

The Nova middleware (`BBSLab\NovaPasswordRotation\Http\Middleware\EnsurePasswordIsNotExpired`)
and the controller stay in this package's namespace.
