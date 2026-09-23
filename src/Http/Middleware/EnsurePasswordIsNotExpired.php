<?php

declare(strict_types=1);

namespace BBSLab\NovaPasswordRotation\Http\Middleware;

use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use BBSLab\LaravelPasswordRotation\PasswordRotationManager;
use Closure;
use Illuminate\Http\Request;
use Laravel\Nova\Nova;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsNotExpired
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('laravel-password-rotation.enabled')) {
            return $next($request);
        }

        $user = Nova::user($request);

        if (! $user instanceof MustRotatePassword || ! $user->passwordHasExpired()) {
            return $next($request);
        }

        // Host-registered escape hatch (e.g. SSO users whose password lives in
        // the identity provider), consulted before redirecting the expired user.
        if (app(PasswordRotationManager::class)->shouldBypass($request, $user)) {
            return $next($request);
        }

        // Never trap the user on our own screen or on the way out. Match Nova's
        // logout by route name so a custom/root-mounted nova.path still works.
        if ($request->routeIs('nova-password-rotation.*') || $request->routeIs('nova.logout')) {
            return $next($request);
        }

        return redirect()->route('nova-password-rotation.expired.show');
    }
}
