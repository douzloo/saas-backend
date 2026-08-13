<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            abort(401);
        }

        if (! in_array(auth()->user()->role, ['admin', 'staff'], true)) {
            abort(403, 'Staff access required');
        }

        return $next($request);
    }
}
