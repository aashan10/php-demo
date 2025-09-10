<?php

declare(strict_types=1);

namespace Elementary\Authentication\Middleware;

use Elementary\Authentication\UserInterface;
use Elementary\Config\ConfigBag;
use Elementary\Http\Middleware\MiddlewareInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;

final class AuthenticateCookieMiddleware implements MiddlewareInterface
{
    public function __construct(private ConfigBag $config)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if ($request->user instanceof UserInterface) {
            return $next($request);
        }

        $cookieValue = $request->cookies->get('elementary_auth');

        if (empty($cookieValue)) {
            return $next($request);
        }

        $parts = explode('|', $cookieValue, 2);

        if (count($parts) !== 2) {
            return $next($request);
        }
        
        [$userId, $token] = $parts;

        if (empty($userId) || empty($token)) {
            return $next($request);
        }

        $userModelClass = $this->config->get('auth.model');
        /** @var \App\Models\User|null $user */
        $user = $userModelClass::find((int) $userId);

        if (!$user || !$user->remember_token || !hash_equals($user->remember_token, $token)) {
            return $next($request);
        }

        $request->user = $user;

        return $next($request);
    }
}
