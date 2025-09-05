<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Directives;

class DirectiveRegistry
{
    private array $directives = [];
    private array $callableDirectives = [];

    public function __construct()
    {
    }

    /**
     * Register a directive class
     */
    public function register(DirectiveInterface $directive): void
    {
        $this->directives[$directive->getName()] = $directive;
    }

    /**
     * Register a simple callable directive (like Blade)
     */
    public function registerCallable(string $name, callable $handler): void
    {
        $this->callableDirectives[$name] = $handler;
    }

    /**
     * Get a directive by name
     */
    public function get(string $name): ?DirectiveInterface
    {
        return $this->directives[$name] ?? null;
    }

    /**
     * Get a callable directive by name
     */
    public function getCallable(string $name): ?callable
    {
        return $this->callableDirectives[$name] ?? null;
    }

    /**
     * Check if a directive exists
     */
    public function has(string $name): bool
    {
        return isset($this->directives[$name]) || isset($this->callableDirectives[$name]);
    }

    /**
     * Get all registered directive names
     */
    public function getNames(): array
    {
        return array_merge(
            array_keys($this->directives),
            array_keys($this->callableDirectives)
        );
    }

    /**
     * Get all directive class instances (for compiler injection)
     */
    public function getAllDirectives(): array
    {
        return array_values($this->directives);
    }

    /**
     * Get all callable directives
     */
    public function getAllCallables(): array
    {
        return $this->callableDirectives;
    }

    /**
     * Remove a directive
     */
    public function remove(string $name): void
    {
        unset($this->directives[$name], $this->callableDirectives[$name]);
    }

    /**
     * Clear all directives
     */
    public function clear(): void
    {
        $this->directives = [];
        $this->callableDirectives = [];
    }

    
}

