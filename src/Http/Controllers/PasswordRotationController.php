<?php

declare(strict_types=1);

namespace BBSLab\NovaPasswordRotation\Http\Controllers;

use BBSLab\NovaPasswordRotation\Contracts\MustRotatePassword;
use BBSLab\NovaPasswordRotation\Rules\PasswordNotReused;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\Validation\Rules\Password;
use Laravel\Nova\Nova;

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
            Password::defaults(),
            function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                if (is_string($value) && Hash::check($value, $user->getAuthPassword())) {
                    $fail((string) trans('nova-password-rotation::validation.different'));
                }
            },
        ];

        if ((int) config('nova-password-rotation.history_count') > 0) {
            $passwordRules[] = new PasswordNotReused($user);
        }

        $rules = ['password' => $passwordRules];

        if (config('nova-password-rotation.require_current_password')) {
            $rules['current_password'] = ['required', 'current_password:'.$guard];
        }

        $validated = $request->validate($rules);

        $user->setAttribute($user->getAuthPasswordName(), Hash::make((string) $validated['password']));
        $user->save();

        return redirect(Nova::path())
            ->with('status', trans('nova-password-rotation::messages.updated'));
    }
}
