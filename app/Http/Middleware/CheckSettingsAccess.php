<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSettingsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('console.settings.gate') || $request->routeIs('console.settings.verify')) {
            return $next($request);
        }

        if ($request->routeIs('console.settings.*') && !session('settings_access_verified', false)) {
            return redirect()->route('console.settings.gate', ['return' => $request->url()]);
        }

        return $next($request);
    }
}
