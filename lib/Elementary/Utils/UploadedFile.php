<?php

declare(strict_types=1);

namespace Elementary\Utils;

final readonly class UploadedFile 
{
    public function __construct(
        public string $name,
        public string $type,
        public string $full_path,
        public string $tmp_name,
        public int $error,
        public int $size,
    ) {
    }

    public function getExtension(): string
    {
        return pathinfo($this->name, PATHINFO_EXTENSION);
    }

    public function getMimeType(): string
    {
        return $this->type;
    }

    public function move(string $destination): bool
    {
        return move_uploaded_file($this->tmp_name, $destination);
    }

    public function delete(): bool
    {
        return unlink($this->tmp_name);
    }
}
