<?php

declare(strict_types=1);

namespace Elementary\Http\Middleware;

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
        
        // Configure PHP session settings to match our configuration
        ini_set('session.gc_maxlifetime', $lifetime);
        ini_set('session.cookie_lifetime', $lifetime);
        ini_set('session.gc_probability', 1);
        ini_set('session.gc_divisor', 100);
        $path = '/';
        $domain = null;
        $secure = $this->config->get('session.secure', false); // Use HTTPS in production
        $httpOnly = true;
        $sameSite = 'Lax'; // CSRF protection
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite
        ]);

        $sessionName = session_name();
        if ($request->cookies->has($sessionName)) {
            $sessionId = $request->cookies->get($sessionName);
            if ($sessionId && preg_match('/^[a-zA-Z0-9,-]+$/', $sessionId)) {
                session_id($sessionId);
            }
        }

        session_start();

        $response = $next($request);

        // Let PHP handle session cookie management automatically
        // No need to manually set session cookies as PHP does this automatically
        // when session_start() or session_regenerate_id() is called

        return $response;
    }
}
