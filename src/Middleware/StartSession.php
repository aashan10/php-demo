<?php

declare(strict_types=1);

namespace App\Middleware;

use Elementary\Http\Middleware\MiddlewareInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Session\SessionManager;
use Elementary\Config\ConfigBag;

class StartSession implements MiddlewareInterface
{
    private SessionManager $sessionManager;
    private ConfigBag $config;

    public function __construct(SessionManager $sessionManager, ConfigBag $config)
    {
        $this->sessionManager = $sessionManager;
        $this->config = $config;
    }

    public function process(Request $request, callable $next): Response
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return $next($request);
        }

        $driver = $this->sessionManager->getDriver();
        session_set_save_handler($driver, true);

        $lifetime = $this->config->get('session.lifetime', 120) * 60;
        $path = '/';
        $domain = null;
        $secure = false;
        $httpOnly = true;
        session_set_cookie_params($lifetime, $path, $domain, $secure, $httpOnly);

        $sessionName = session_name();
        if ($request->cookies->has($sessionName)) {
            $sessionId = $request->cookies->get($sessionName);
            if ($sessionId && ctype_alnum($sessionId)) {
                session_id($sessionId);
            }
        }

        session_start(['use_cookies' => false]);

        $response = $next($request);

        $currentSessionId = session_id();
        if (session_status() === PHP_SESSION_ACTIVE && $currentSessionId) {
            $originalSessionId = $request->cookies->get($sessionName);
            if ($originalSessionId !== $currentSessionId) {
                 $response->cookies->set($sessionName, $currentSessionId);
            }
        }

        return $response;
    }
}
