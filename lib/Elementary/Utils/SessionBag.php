<?php

declare(strict_types=1);

namespace Elementary\Utils;

/**
 * Provides a robust, object-oriented wrapper for the $_SESSION superglobal.
 * Ensures that changes are written directly to the session.
 */
class SessionBag
{
    public function __construct()
    {
        // The StartSession middleware is now responsible for starting the session.
        // This constructor is intentionally left empty.
    }

    /**
     * Gets a value from the session.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Sets a value in the session.
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Checks if a key exists in the session.
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Removes a value from the session.
     */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Gets all values from the session.
     */
    public function all(): array
    {
        return $_SESSION;
    }

    /**
     * Clears all session data.
     */
    public function clear(): void
    {
        $_SESSION = [];
    }

    /**
     * Destroys the entire session.
     */
    public function destroy(): bool
    {
        $this->clear();
        return session_destroy();
    }
}
