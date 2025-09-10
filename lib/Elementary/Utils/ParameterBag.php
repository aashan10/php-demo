<?php

declare(strict_types=1);

namespace Elementary\Utils;

class ParameterBag {

    public function __construct(
        private array $parameters = []
    ) {}

    public function get(string $key, mixed $default = null): mixed {
        return $this->parameters[$key] ?? $default;
    }

    public function has(string $key): bool {
        return isset($this->parameters[$key]);
    }

    public function all(): array {
        return $this->parameters;
    }

    public function set(string $key, mixed $value): void {
        $this->parameters[$key] = $value;
    }

    public function remove(string $key): void {
        unset($this->parameters[$key]);
    }

    public function clear(): void {
        $this->parameters = [];
    }

    public function only(array $keys): array 
    {
        return array_intersect_key($this->parameters, array_flip($keys));
    }

    public function isEmpty(): bool {
        return empty($this->parameters);
    }
}
