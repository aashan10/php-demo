<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\AST;

class EchoNode extends Node
{
    public function __construct(public string $expression, public bool $raw = false) {}
    
    public function getType(): string 
    { 
        return 'echo'; 
    }
    
    public function accept(NodeVisitor $visitor)
    {
        return $visitor->visitEcho($this);
    }
}
