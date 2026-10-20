<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects student athletes whose accounts were marked for a mandatory
 * password change (e.g. after an administrator-assisted reset) to the change
 * password page. Administrators and already-compliant athletes pass through.
 *
 * The change password page and logout are excluded so the redirect cannot
 * loop.
 */
class ForcePasswordChangeMiddleware
{
    /**
     * Routes a forced-change athlete may still reach before resetting.
     *
     * @var array<string>
     */
    private const ALLOWED_ROUTE_NAMES = [
        'student.change-password',
        'student.change-password.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && $user->role === 'Student'
            && $user->needsPasswordChange()
            && ! in_array($request->route()?->getName(), self::ALLOWED_ROUTE_NAMES, true)
        ) {
            return redirect()->route('student.change-password');
        }

        return $next($request);
    }
}
