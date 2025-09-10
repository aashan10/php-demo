<?php

declare(strict_types=1);

namespace Elementary\Session\Drivers;

class FileSessionDriver implements SessionDriverInterface
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function open(string $path, string $name): bool
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $file = $this->path . '/' . $id;
        if (file_exists($file)) {
            $content = file_get_contents($file);
            return $content !== false ? $content : '';
        }
        return '';
    }

    public function write(string $id, string $data): bool
    {
        return file_put_contents($this->path . '/' . $id, $data) !== false;
    }

    public function destroy(string $id): bool
    {
        $file = $this->path . '/' . $id;
        if (file_exists($file)) {
            unlink($file);
        }
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $count = 0;
        foreach (glob($this->path . '/*') as $file) {
            if (is_file($file) && filemtime($file) + $max_lifetime < time()) {
                unlink($file);
                $count++;
            }
        }
        return $count;
    }
}
