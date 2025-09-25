<?php

declare(strict_types=1);

namespace Elementary\Utils;

use Elementary\Config\ConfigBag;
use RuntimeException;

/**
 * Manages the generation and validation of CSRF tokens.
 */
class Csrf
{
    private const SESSION_KEY = '_token';

    public function __construct(private SessionBag $session, private ConfigBag $config)
    {
    }

    /**
     * Generates a new CSRF token, stores it in the session, and returns it.
     * If a token already exists and is valid, it returns that one.
     * If it is expired, it regenerates a new one.
     */
    public function getToken(): string
    {
        $tokenData = $this->session->get(self::SESSION_KEY);

        if (!$this->session->has(self::SESSION_KEY) || $this->isTokenExpired($tokenData['timestamp'] ?? 0)) {
            return $this->regenerateToken();
        }

        return $tokenData['value'];
    }

    /**
     * Validates the given token against the one stored in the session.
     */
    public function validate(?string $submittedToken): bool
    {
        if (!$submittedToken) {
            return false;
        }

        $sessionTokenData = $this->session->get(self::SESSION_KEY);

        if (!is_array($sessionTokenData) || !isset($sessionTokenData['value'], $sessionTokenData['timestamp'])) {
            return false;
        }

        if ($this->isTokenExpired($sessionTokenData['timestamp'])) {
            return false;
        }

        return hash_equals($sessionTokenData['value'], $submittedToken);
    }

    /**
     * Generates and stores a new random token in the session.
     */
    public function regenerateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set(self::SESSION_KEY, [
            'value' => $token,
            'timestamp' => time(),
        ]);
        return $token;
    }

    /**
     * Checks if the token timestamp is older than the configured lifetime.
     */
    private function isTokenExpired(int $timestamp): bool
    {
        $lifetime = $this->config->get('session.csrf_lifetime', 10) * 60; // Default to 10 minutes in seconds
        return (time() - $timestamp) > $lifetime;
    }
}
