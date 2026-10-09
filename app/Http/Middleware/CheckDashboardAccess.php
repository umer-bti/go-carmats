<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDashboardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('console.dashboard.gate') || $request->routeIs('console.dashboard.verify')) {
            return $next($request);
        }

        if ($request->routeIs('console.dashboard') && ! session('dashboard_access_verified', false)) {
            return redirect()->route('console.dashboard.gate', ['return' => $request->url()]);
        }

        return $next($request);
    }
}
