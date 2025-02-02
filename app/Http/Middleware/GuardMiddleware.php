<?php

namespace App\Http\Middleware;

use Auth;
use Closure;

class GuardMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        // Map guards to their corresponding models
        $guardModelMap = [
            'passenger' => \App\Models\Passenger::class,
            'driver' => \App\Models\Driver::class,
            'web' => \App\Models\User::class,
        ];

        // Check if the guard exists and if the authenticated user matches the guard's model
        if (isset($guardModelMap[$guard]) && $request->user() instanceof $guardModelMap[$guard]) {
            $request->merge(['auth_user' => Auth::guard($guard)->user()]);
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => "Unauthorized Access",
            'data' => null,
        ], 401);
    }

}
