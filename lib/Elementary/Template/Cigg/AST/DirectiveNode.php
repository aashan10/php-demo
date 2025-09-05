<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\AST;

class DirectiveNode extends Node
{
    public function __construct(
        public string $name,
        public string $expression = '',
        public array $children = []
    ) {}
    
    public function getType(): string 
    { 
        return 'directive'; 
    }
    
    public function accept(NodeVisitor $visitor)
    {
        return $visitor->visitDirective($this);
    }
}


