<?php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost:3000,127.0.0.1:3000,red-nextjs-shadcn.vercel.app')),

    'guard' => ['web'],

    'expiration' => null,

    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],

    // Add this line if it's missing to ensure the prefix is standard
    'prefix' => 'sanctum',
];
