<?php

declare(strict_types=1);

namespace Elementary\Utils;

class FlashBag
{
    private SessionBag $session;

    public function __construct(SessionBag $session)
    {
        $this->session = $session;
    }

    public function add(string $key, mixed $value): void
    {
        $this->session->set('flash_' . $key, $value);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->session->get('flash_' . $key, $default);
        $this->session->remove('flash_' . $key);
        return $value;
    }

    public function has(string $key): bool
    {
        return $this->session->has('flash_' . $key);
    }

    public function all(): array
    {
        $allFlash = [];
        foreach ($this->session->all() as $key => $value) {
            if (str_starts_with($key, 'flash_')) {
                $originalKey = substr($key, 6);
                $allFlash[$originalKey] = $value;
                $this->session->remove($key);
            }
        }
        return $allFlash;
    }
}
