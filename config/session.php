<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Session Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default session "driver" that will be used on
    | requests. By default, we will use the lightweight file driver but
    | you may specify any of the other wonderful drivers provided here.
    |
    | Supported: "file", "database"
    |
    */
    'driver' => 'database',

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime
    |--------------------------------------------------------------------------
    |
    | Here you may specify the number of minutes that you wish the session
    | to be allowed to remain idle before it expires. If you want them
    | to immediately expire on browser close, set that option.
    |
    */
    'lifetime' => 120, // in minutes

    /*
    |--------------------------------------------------------------------------
    | CSRF Token Lifetime
    |--------------------------------------------------------------------------
    |
    | This is the number of minutes that the CSRF token should be considered
    | valid. If this expires, a new token will be generated for the user.
    |
    */
    'csrf_lifetime' => 10, // in minutes

    'expire_on_close' => false,

    /*
    |--------------------------------------------------------------------------
    | Session File Location
    |--------------------------------------------------------------------------
    |
    | When using the native session driver, we need a location where session
    | files may be stored. A default has been set for you but a different
    | location may be specified. This is only for file based sessions.
    |
    */
    'files' => BASE_PATH . '/cache/sessions',

    /*
    |--------------------------------------------------------------------------
    | Session Database Table
    |--------------------------------------------------------------------------
    |
    | When using the "database" session driver, you may specify the table we
    | should use to manage the sessions. Of course, a sensible default is
    | provided for you; however, you are free to change this as needed.
    |
    */
    'table' => 'sessions',
];
