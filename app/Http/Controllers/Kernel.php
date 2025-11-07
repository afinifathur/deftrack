<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    // ...
    protected $middlewareAliases = [
        'auth'     => \App\Http\Middleware\Authenticate::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        // ---- ALIAS KITA ----
        'role'     => \App\Http\Middleware\RoleMiddleware::class,
    ];
}
