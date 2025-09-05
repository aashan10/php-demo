<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Token;

class Token
{
    public function __construct(
        public string $type,
        public string $value,
        public int $line = 1,
        public int $column = 1
    ) {}

    public function __toString(): string
    {
        return "[{$this->type}] '{$this->value}' at {$this->line}:{$this->column}";
    }
}
