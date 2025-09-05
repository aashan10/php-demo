<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\AST;

interface NodeVisitor
{
    public function visitDocument(DocumentNode $node);
    public function visitText(TextNode $node);
    public function visitEcho(EchoNode $node);
    public function visitDirective(DirectiveNode $node);
}
