<?php

declare(strict_types=1);

namespace Elementary\Spark;

class SparkComponentRegistry
{
    private static array $components = [];

    /**
     * Register a live component
     */
    public static function register(string $name, string $className): void
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Component class {$className} does not exist");
        }

        if (!is_subclass_of($className, SparkComponent::class)) {
            throw new \InvalidArgumentException("Component {$className} must extend " . SparkComponent::class);
        }

        self::$components[$name] = $className;
    }

    /**
     * Get component class by name
     */
    public static function get(string $name): ?string
    {
        return self::$components[$name] ?? null;
    }

    /**
     * Check if component is registered
     */
    public static function has(string $name): bool
    {
        return isset(self::$components[$name]);
    }

    /**
     * Get all registered components
     */
    public static function all(): array
    {
        return self::$components;
    }

    /**
     * Clear all registrations (useful for testing)
     */
    public static function clear(): void
    {
        self::$components = [];
    }
}
