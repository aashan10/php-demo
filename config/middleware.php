<?php

use App\Middleware\Authenticate;
use App\Middleware\StartSession;
use App\Middleware\TrimStrings;

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
        'auth' => Authenticate::class,
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
            StartSession::class,
            TrimStrings::class,
        ],
        'api' => [
            // Middleware for API routes can be added here
        ]
    ]
];
