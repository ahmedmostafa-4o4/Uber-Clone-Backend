<?php

namespace App\Http\Middleware;

use Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifiedMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        // Driver authentication and admin verification
        if ($user instanceof \App\Models\Driver && $user->is_verified == 1) {
            return $next($request);
        }

        // Passenger authentication and email verification
        if ($user instanceof \App\Models\Passenger && $user->email_verified_at !== null) {
            return $next($request);
        }

        // Admin (web user) authentication
        if ($user instanceof \App\Models\User) {
            return $next($request);
        }

        // Unauthorized response
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized Access, Please verify your account first.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
