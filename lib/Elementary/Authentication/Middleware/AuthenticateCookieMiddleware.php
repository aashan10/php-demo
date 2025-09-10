<?php

declare(strict_types=1);

namespace Elementary\Authentication\Middleware;

use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use Elementary\Authentication\UserInterface;
use Elementary\Http\Middleware\MiddlewareInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;

final class AuthenticateCookieMiddleware implements MiddlewareInterface
{
    public function __construct(private UserRepositoryInterface $userRepository)
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

        /** @var User|null $user */
        $user = User::find((int) $userId);

        if (!$user || !$user->remember_token || !hash_equals($user->remember_token, $token)) {
            return $next($request);
        }

        $request->user = $user;

        return $next($request);
    }
}
