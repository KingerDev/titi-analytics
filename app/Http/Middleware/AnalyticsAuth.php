<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AnalyticsAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('analytics_authed')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
