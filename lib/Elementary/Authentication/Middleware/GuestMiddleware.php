<?php

declare(strict_types=1);

namespace Elementary\Authentication\Middleware;

use Elementary\Authentication\UserInterface;
use Elementary\Http\Middleware\MiddlewareInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;

final class GuestMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        if ($request->user instanceof UserInterface) {
            return new Response(302, '', ['Location' => '/']);
        }
        return $next($request);

    }

}
