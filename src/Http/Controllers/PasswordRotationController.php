<?php

declare(strict_types=1);

namespace BBSLab\NovaPasswordRotation\Http\Controllers;

use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use BBSLab\LaravelPasswordRotation\Rules\PasswordNotReused;
use BBSLab\NovaPasswordRotation\Notifications\ResetPassword as ResetPasswordNotification;
use Closure;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Laravel\Nova\Nova;
use LogicException;

class PasswordRotationController
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = Nova::user($request);

        if (! $user instanceof MustRotatePassword || ! $user->passwordHasExpired()) {
            return redirect(Nova::path());
        }

        return ViewFactory::make('nova-password-rotation::expired');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Nova::user($request);

        if (! $user instanceof MustRotatePassword) {
            return redirect(Nova::path());
        }

        $guard = (string) (config('nova.guard') ?: config('auth.defaults.guard'));

        $passwordRules = [
            'required',
            'string',
            'confirmed',
            PasswordRule::defaults(),
            function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                if (is_string($value) && Hash::check($value, $user->getAuthPassword())) {
                    $fail((string) trans('laravel-password-rotation::validation.different'));
                }
            },
        ];

        if ((int) config('laravel-password-rotation.history_count') > 0) {
            $passwordRules[] = new PasswordNotReused($user);
        }

        $rules = ['password' => $passwordRules];

        if (config('laravel-password-rotation.require_current_password')) {
            $rules['current_password'] = ['required', 'current_password:'.$guard];
        }

        $validated = $request->validate($rules);

        $user->setAttribute($user->getAuthPasswordName(), Hash::make((string) $validated['password']));
        $user->save();

        return redirect(Nova::path())
            ->with('status', trans('nova-password-rotation::messages.updated'));
    }

    /**
     * Email a password reset link and sign the user out, so an expired password
     * is renewed through the standard reset flow instead of on the Nova screen.
     * Used when expiry_action is "reset".
     */
    public function reset(Request $request): RedirectResponse
    {
        $user = Nova::user($request);

        if (! $user instanceof MustRotatePassword) {
            return redirect(Nova::path());
        }

        $email = $user->getEmailForPasswordReset();

        if (Route::has('nova.pages.password.reset')) {
            // Point the link at Nova's own reset page. The default notification
            // would build route('password.reset'), which a Nova app does not
            // define (Nova names its page route nova.pages.password.reset).
            Password::sendResetLink(
                ['email' => $email],
                function (CanResetPassword $notifiable, string $token): void {
                    $notification = new ResetPasswordNotification($token);
                    $notification->url = route('nova.pages.password.reset', [
                        'token' => $token,
                        'email' => $notifiable->getEmailForPasswordReset(),
                    ]);
                    $notifiable->notify($notification);
                },
            );
        } elseif (Route::has('password.reset')) {
            // A standard Laravel reset flow is configured; its notification URL
            // already resolves, so the default send is correct.
            Password::sendResetLink(['email' => $email]);
        } else {
            throw new LogicException(
                'The "reset" expiry action needs a reset page: enable Nova password reset (Nova::routes()->withPasswordResetRoutes()) or define a standard "password.reset" route.'
            );
        }

        // Log out so the emailed reset link (a guest route) is reachable, then
        // rotate the session as Laravel's own logout does.
        $guard = (string) (config('nova.guard') ?: config('auth.defaults.guard'));
        Auth::guard($guard)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Nova::url('/login') is the login-screen path across Nova 4 and 5 (the
        // named "nova.login" route is the POST handler in Nova 5, not the page).
        // Nova's login is a Vue SPA, so it will not render this Laravel flash; it
        // is a best-effort signal only — the emailed reset link is the real one.
        return redirect(Nova::url('/login'))
            ->with('status', trans('nova-password-rotation::messages.reset_sent'));
    }
}
