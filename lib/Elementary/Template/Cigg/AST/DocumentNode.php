<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\AST;

class DocumentNode extends Node
{
    public function __construct(public array $children = []) {}
    
    public function getType(): string 
    { 
        return 'document'; 
    }
    
    public function accept(NodeVisitor $visitor)
    {
        return $visitor->visitDocument($this);
    }
}
