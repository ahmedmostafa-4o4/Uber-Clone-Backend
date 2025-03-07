<?php

namespace App\Http\Middleware;

use Closure;
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

        if ($user instanceof \App\Models\Driver && $user->is_verified == 1) {
            return $next($request);
        }

        if ($user instanceof \App\Models\Passenger && $user->email_verified_at !== null) {
            return $next($request);
        }

        if ($user instanceof \App\Models\User) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized Access, Please verify your account first.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
