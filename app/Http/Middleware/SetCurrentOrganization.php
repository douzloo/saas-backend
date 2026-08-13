<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $organization = $user->organizations()->first();

        if (! $organization) {
            return response()->json([
                'message' => 'Organization not found',
            ], 404);
        }

        app()->instance(
            'currentOrganization',
            $organization
        );

        app()->instance(
            'currentOrganizationRole',
            $organization->pivot->role
        );

        app()->instance(
            'currentOrganizationPermissions',
            $organization->pivot->permissions ?? []
        );

        return $next($request);
    }
}
