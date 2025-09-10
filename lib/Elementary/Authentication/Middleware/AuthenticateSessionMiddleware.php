<?php

declare(strict_types=1);

namespace Elementary\Authentication\Middleware;

use Elementary\Authentication\UserInterface;
use Elementary\Http\Middleware\MiddlewareInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Model\User;

final class AuthenticateSessionMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        if ($request->user instanceof UserInterface) {
            return $next($request);
        }

        $session = $request->session;

        if (!$session->has('user_id')) {
            return new Response(302, '', ['Location' => '/login']);
        }

        $user = User::find($session->get('user_id'));

        if (!$user instanceof UserInterface) {
            $session->remove('user_id');
            return new Response(302, '', ['Location' => '/login']);
        }

        $request->user = $user;

        return $next($request);
    }

}
