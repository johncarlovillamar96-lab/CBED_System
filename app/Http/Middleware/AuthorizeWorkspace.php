<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeWorkspace
{
    public function handle(Request $request, Closure $next, string $area = 'workspace'): Response
    {
        $user = $request->user();
        $read = in_array($request->method(), ['GET', 'HEAD'], true);

        $allowed = $user->isAdmin() || match ($area) {
            'monitoring' => $user->canManageMonitoring() || ($user->role === User::NON_ADMIN && $read),
            'monitoring-entry' => $user->canManageMonitoring(),
            'workspace' => $user->role === User::NON_ADMIN && $read,
            default => false,
        };

        abort_unless($allowed, 403);

        return $next($request);
    }
}
