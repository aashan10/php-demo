<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\AST;

class TextNode extends Node
{
    public function __construct(public string $content) {}
    
    public function getType(): string 
    { 
        return 'text'; 
    }
    
    public function accept(NodeVisitor $visitor)
    {
        return $visitor->visitText($this);
    }
}

