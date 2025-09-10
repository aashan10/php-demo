<?php

declare(strict_types=1);

namespace Elementary\Authentication\Middleware;

use Elementary\Authentication\UserInterface;
use Elementary\Http\Middleware\MiddlewareInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Model\User;

final class AuthenticateApiMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        if ($request->user instanceof UserInterface) {
            return $next($request);
        }

        $headers = $request->headers;

        if (!$headers->has('x-elementray-api-token')) {
            return new Response(401, 'Unauthorized');
        }
        return $next($request);
    }

}
