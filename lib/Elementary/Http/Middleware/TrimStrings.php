<?php

declare(strict_types=1);

namespace Elementary\Http\Middleware;

use Elementary\Http\Middleware\MiddlewareInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;

/**
 * Trims leading and trailing whitespace from all POST fields.
 */
class TrimStrings implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        foreach ($request->post->all() as $key => $value) {
            if (is_string($value)) {
                $request->post->set($key, trim($value));
            }
        }

        return $next($request);
    }
}
