<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EnsureTokenIsValid
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): RedirectResponse|Response
    {
        if ($request->bearerToken() === config('project.deployer.token')) {
            return $next($request);
        }

        return response()->redirectTo('/');
    }
}
