<?php

declare(strict_types=1);

namespace Elementary\Http\Middleware;

use Elementary\Http\Request;
use Elementary\Http\Response;

interface MiddlewareInterface
{
    /**
     * Process an incoming server request.
     *
     * Processes an incoming server request in order to produce a response.
     * If unable to produce the response itself, it may delegate to the provided
     * request handler to do so.
     */
    public function process(Request $request, callable $next): Response;
}
