<?php

declare(strict_types=1);

namespace Elementary\Config;

use Elementary\DI\Exceptions\ContainerException;

class ConfigBag
{
    private array $items = [];

    public function __construct(string $configPath)
    {
        $this->loadConfig($configPath);
    }

    private function loadConfig(string $configPath): void
    {
        if (!is_dir($configPath)) {
            throw new ContainerException("Config directory not found: {$configPath}");
        }

        $files = glob($configPath . '/*.php');

        foreach ($files as $file) {
            $fileName = pathinfo($file, PATHINFO_FILENAME);
            $this->items[$fileName] = require $file;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $current = $this->items;

        foreach ($parts as $part) {
            if (is_array($current) && array_key_exists($part, $current)) {
                $current = $current[$part];
            } else {
                return $default;
            }
        }

        return $current;
    }

    public function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $current = & $this->items;

        foreach ($parts as $part) {
            if (!is_array($current)) {
                $current = [];
            }
            $current = & $current[$part];
        }

        $current = $value;
    }

    public function all(): array
    {
        return $this->items;
    }
}
