<?php

use Elementary\Http\Middleware\StartSession;
use Elementary\Http\Middleware\TrimStrings;
use Elementary\Http\Middleware\EncryptCookies;
use Elementary\Authentication\Middleware\AuthenticateApiMiddleware;
use Elementary\Authentication\Middleware\AuthenticateCookieMiddleware;
use Elementary\Authentication\Middleware\AuthenticateSessionMiddleware;
use Elementary\Http\Middleware\VerifyCsrfToken;

return [
    /*
    |--------------------------------------------------------------------------
    | Middleware Aliases
    |--------------------------------------------------------------------------
    |
    | Here you can register aliases for middleware classes. These aliases
    | can be used to conveniently assign middleware to routes and groups.
    |
    */
    'aliases' => [
        'trim' => TrimStrings::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Groups
    |--------------------------------------------------------------------------
    |
    | Here you can define groups of middleware that can be applied to routes
    | with a single key. For example, the "web" group contains middleware
    | commonly applied to all web-based routes.
    |
    */
    'groups' => [
        'web' => [
            EncryptCookies::class,
            StartSession::class,
            VerifyCsrfToken::class,
            TrimStrings::class,
        ],
        'api' => [
            // Middleware for API routes can be added here
        ],
        'auth' => [
            AuthenticateSessionMiddleware::class,
            AuthenticateCookieMiddleware::class,
            AuthenticateApiMiddleware::class,
        ]
    ]
];
