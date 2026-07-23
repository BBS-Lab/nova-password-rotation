<?php

declare(strict_types=1);

return [

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
    | Behaviour on an expired password
    |--------------------------------------------------------------------------
    |
    | What the forced-change screen does when a user's password has expired:
    |
    |   'change' — show the in-panel change-password form (the default).
    |   'reset'  — show a single button that emails a password reset link and
    |              signs the user out, so they set a new password via the
    |              standard reset flow instead of on the Nova screen.
    |
    | The 'reset' action requires the authenticatable to implement
    | Illuminate\Contracts\Auth\CanResetPassword (Laravel's default User does),
    | and a reset page for the link to point at: enable Nova password reset
    | (Nova::routes()->withPasswordResetRoutes()) or define a "password.reset" route.
    |
    */

    'expiry_action' => env('PASSWORD_ROTATION_EXPIRY_ACTION', 'change'),

];
