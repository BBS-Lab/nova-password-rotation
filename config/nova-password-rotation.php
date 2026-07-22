<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | When disabled, the package becomes completely inert: the middleware never
    | forces a password change and no expiry warnings are shown. Handy for
    | local development or staging environments.
    |
    */

    'enabled' => env('PASSWORD_ROTATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Password history morph key type
    |--------------------------------------------------------------------------
    |
    | The key type for the "password_histories.authenticatable_id" column. Leave
    | null to follow Laravel's global default (Schema::defaultMorphKeyType()),
    | which is a BIGINT unless you have switched it. Set to "uuid" or "ulid" if
    | your authenticatable uses string primary keys, so the migration provisions
    | a matching column instead of silently truncating string keys.
    |
    */

    'morph_key_type' => env('PASSWORD_ROTATION_MORPH_KEY_TYPE'),

    /*
    |--------------------------------------------------------------------------
    | Automatic Nova middleware registration
    |--------------------------------------------------------------------------
    |
    | By default the package appends its middleware to Nova's web middleware
    | stack (config('nova.middleware')) so it "just works" after install. Set
    | this to false if you prefer to register the middleware yourself.
    |
    */

    'auto_register_middleware' => env('PASSWORD_ROTATION_AUTO_MIDDLEWARE', true),

    /*
    |--------------------------------------------------------------------------
    | Rotation period (days)
    |--------------------------------------------------------------------------
    |
    | How many days a password stays valid before the user is forced to change
    | it. Counted from the value stored in the "column" below.
    |
    */

    'days' => (int) env('PASSWORD_ROTATION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Timestamp column
    |--------------------------------------------------------------------------
    |
    | The column, on any model implementing MustRotatePassword, that stores the
    | moment the password was last changed. Override it in the model via
    | passwordRotationColumn() if a single value is not enough.
    |
    */

    'column' => env('PASSWORD_ROTATION_COLUMN', 'password_changed_at'),

    /*
    |--------------------------------------------------------------------------
    | Force a change on first login
    |--------------------------------------------------------------------------
    |
    | When true, a null timestamp is treated as "expired", so admin-provisioned
    | accounts must set their own password before using Nova. The publishable
    | migration backfills existing rows to now() to avoid a mass lock-out.
    |
    */

    'force_on_first_login' => env('PASSWORD_ROTATION_FORCE_FIRST_LOGIN', true),

    /*
    |--------------------------------------------------------------------------
    | Require the current password
    |--------------------------------------------------------------------------
    |
    | Require users to confirm their current password on the change screen.
    | Recommended; disable only if you have a good reason to.
    |
    */

    'require_current_password' => env('PASSWORD_ROTATION_REQUIRE_CURRENT', true),

    /*
    |--------------------------------------------------------------------------
    | Password reuse prevention
    |--------------------------------------------------------------------------
    |
    | Number of previous passwords remembered (hashed) and rejected on the
    | change screen. Set to 0 to disable history entirely.
    |
    */

    'history_count' => (int) env('PASSWORD_ROTATION_HISTORY_COUNT', 3),

    /*
    |--------------------------------------------------------------------------
    | Expiry warning window (days)
    |--------------------------------------------------------------------------
    |
    | Show a Nova notice this many days before the password actually expires.
    | Set to 0 to disable the warning.
    |
    */

    'warn_days' => (int) env('PASSWORD_ROTATION_WARN_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Route URI prefix
    |--------------------------------------------------------------------------
    |
    | The change screen lives under Nova's path at "{nova}/{prefix}/expired".
    | Change it only if it collides with one of your own routes.
    |
    */

    'route_prefix' => env('PASSWORD_ROTATION_ROUTE_PREFIX', 'password-rotation'),

    /*
    |--------------------------------------------------------------------------
    | Rotatable models
    |--------------------------------------------------------------------------
    |
    | The authenticatable models the "password-rotation:report" command scans.
    | The middleware itself does not use this list: it works off the interface
    | on whatever user Nova has authenticated, so any model is supported. Only
    | classes that exist and implement MustRotatePassword are actually scanned.
    |
    */

    'models' => [
        'App\\Models\\User',
    ],

];
