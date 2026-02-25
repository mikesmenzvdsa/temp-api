<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * These paths will allow the Next.js proxy to hit the backend 
     * even if the XSRF-TOKEN cookie isn't perfectly synced yet.
     *
     * @var array<int, string>
     */
    protected $except = [
        'sanctum/csrf-cookie',
        'login',
        'api/login',      // Standard API login path
        'backend/login',  // Your specific proxy path
        'backend/sanctum/csrf-cookie',
    ];
}