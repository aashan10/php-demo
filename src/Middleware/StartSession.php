<?php

declare(strict_types=1);

namespace App\Middleware;

use Elementary\Http\Middleware\MiddlewareInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;

/**
 * Starts the session if it is not already active.
 */
class StartSession implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $next($request);
    }
}
