<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\AST;

class ComponentNode extends Node
{
    public function __construct(
        public string $tagName,
        public array $attributes,
        public ?Node $slot = null
    ) {
    }

    public function getType(): string
    {
        return 'component';
    }

    public function accept(NodeVisitor $visitor)
    {
        return $visitor->visitComponentNode($this);
    }
}