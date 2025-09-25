<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg;

use Elementary\Template\Cigg\AST\Node;

abstract class Component
{
    public array $attributes = [];
    public ?string $slot = null;

    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
        if (isset($attributes['slot'])) {
            $this->slot = $attributes['slot'];
        }
    }

    abstract public function render(): string;
}