<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\AST;

abstract class Node
{
    abstract public function getType(): string;
    abstract public function accept(NodeVisitor $visitor);
}
