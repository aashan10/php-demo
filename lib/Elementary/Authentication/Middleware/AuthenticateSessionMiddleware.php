<?php

declare(strict_types=1);

namespace Elementary\Authentication\Middleware;

use Elementary\Authentication\UserInterface;
use Elementary\Config\ConfigBag;
use Elementary\Http\Middleware\MiddlewareInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;

final class AuthenticateSessionMiddleware implements MiddlewareInterface
{
    public function __construct(private ConfigBag $config)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if ($request->user instanceof UserInterface) {
            return $next($request);
        }

        $session = $request->session;

        if (!$session->has('user_id')) {
            $session->set('redirection_url_after_login', (string)$request->uri());
            return new Response(302, '', ['Location' => '/login']);
        }

        $userModelClass = $this->config->get('auth.model');
        $user = $userModelClass::find($session->get('user_id'));

        if (!$user instanceof UserInterface) {
            $session->remove('user_id');
            $session->set('redirection_url_after_login', (string)$request->uri());
            return new Response(302, '', ['Location' => '/login']);
        }

        $request->user = $user;

        return $next($request);
    }

}
