<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckStitchersAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('console.stitchers.gate') || $request->routeIs('console.stitchers.verify')) {
            return $next($request);
        }

        if ($request->routeIs('console.stitchers.*') && ! session('stitchers_access_verified', false)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stitchers access verification is required.',
                    'requires_stitchers_access' => true,
                    'gate_url' => route('console.stitchers.gate', ['return' => $request->url()]),
                ], 403);
            }

            return redirect()->route('console.stitchers.gate', ['return' => $request->url()]);
        }

        return $next($request);
    }
}
