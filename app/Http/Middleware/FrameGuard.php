<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FrameGuard
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Set X-Frame-Options header to prevent iframe embedding
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        
        // Set Content-Security-Policy header
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        
        // Set CORS headers for AJAX requests
        if ($request->ajax() || $request->expectsJson()) {
            $response->headers->set('Access-Control-Allow-Origin', $request->header('Origin') ?? '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization, X-CSRF-TOKEN');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
        
        return $response;
    }
} 