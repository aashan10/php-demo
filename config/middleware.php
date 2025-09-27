<?php

use Elementary\Authentication\Middleware\GuestMiddleware;
use Elementary\Http\Middleware\StartSession;
use Elementary\Http\Middleware\TrimStrings;
use Elementary\Http\Middleware\EncryptCookies;
use Elementary\Authentication\Middleware\AuthenticateApiMiddleware;
use Elementary\Authentication\Middleware\AuthenticateCookieMiddleware;
use Elementary\Authentication\Middleware\AuthenticateSessionMiddleware;
use Elementary\Http\Middleware\VerifyCsrfToken;
use Elementary\Spark\Middleware\ValidateJsonPayload;
use Elementary\Spark\Middleware\ValidateSparkRequest;
use Elementary\Spark\Middleware\ValidateSparkChecksum;

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
        ],
        'guest' => [
            GuestMiddleware::class,
        ],
        'spark' => [
            ValidateJsonPayload::class,
            ValidateSparkRequest::class,
            // ValidateSparkChecksum::class, // TODO: Enable once client-side checksum calculation is implemented
        ]
    ]
];
