<?php

declare(strict_types=1);

namespace Tests\Unit\Template\Cigg\AST;

use Elementary\Template\Cigg\AST\DocumentNode;
use Elementary\Template\Cigg\AST\TextNode;
use Elementary\Template\Cigg\AST\EchoNode;
use Elementary\Template\Cigg\AST\DirectiveNode;
use Elementary\Template\Cigg\AST\ComponentNode;
use Elementary\Template\Cigg\AST\NodeVisitor;
use PHPUnit\Framework\TestCase;

class NodeTest extends TestCase
{
    public function test_document_node_creation()
    {
        $children = [
            new TextNode('Hello'),
            new EchoNode('$name'),
        ];
        
        $node = new DocumentNode($children);
        
        $this->assertEquals('document', $node->getType());
        $this->assertCount(2, $node->children);
        $this->assertSame($children, $node->children);
    }

    public function test_document_node_empty_children()
    {
        $node = new DocumentNode();
        
        $this->assertEquals('document', $node->getType());
        $this->assertEmpty($node->children);
        $this->assertIsArray($node->children);
    }

    public function test_text_node_creation()
    {
        $content = 'Hello, World!';
        $node = new TextNode($content);
        
        $this->assertEquals('text', $node->getType());
        $this->assertEquals($content, $node->content);
    }

    public function test_text_node_empty_content()
    {
        $node = new TextNode('');
        
        $this->assertEquals('text', $node->getType());
        $this->assertEquals('', $node->content);
    }

    public function test_text_node_multiline_content()
    {
        $content = "Line 1\nLine 2\nLine 3";
        $node = new TextNode($content);
        
        $this->assertEquals('text', $node->getType());
        $this->assertEquals($content, $node->content);
        $this->assertStringContainsString("\n", $node->content);
    }

    public function test_echo_node_creation()
    {
        $expression = '$user->name';
        $node = new EchoNode($expression);
        
        $this->assertEquals('echo', $node->getType());
        $this->assertEquals($expression, $node->expression);
        $this->assertFalse($node->raw);
    }

    public function test_echo_node_raw_mode()
    {
        $expression = '$htmlContent';
        $node = new EchoNode($expression, true);
        
        $this->assertEquals('echo', $node->getType());
        $this->assertEquals($expression, $node->expression);
        $this->assertTrue($node->raw);
    }

    public function test_echo_node_complex_expression()
    {
        $expression = '$items[0]->getProperty() ?? "default"';
        $node = new EchoNode($expression);
        
        $this->assertEquals($expression, $node->expression);
        $this->assertFalse($node->raw);
    }

    public function test_directive_node_simple()
    {
        $node = new DirectiveNode('if', '$condition');
        
        $this->assertEquals('directive', $node->getType());
        $this->assertEquals('if', $node->name);
        $this->assertEquals('$condition', $node->expression);
        $this->assertEmpty($node->children);
    }

    public function test_directive_node_without_expression()
    {
        $node = new DirectiveNode('endif');
        
        $this->assertEquals('directive', $node->getType());
        $this->assertEquals('endif', $node->name);
        $this->assertEquals('', $node->expression);
        $this->assertEmpty($node->children);
    }

    public function test_directive_node_with_children()
    {
        $children = [
            new TextNode('Content inside directive'),
            new EchoNode('$variable')
        ];
        
        $node = new DirectiveNode('section', '"main"', $children);
        
        $this->assertEquals('directive', $node->getType());
        $this->assertEquals('section', $node->name);
        $this->assertEquals('"main"', $node->expression);
        $this->assertCount(2, $node->children);
        $this->assertSame($children, $node->children);
    }

    public function test_component_node_self_closing()
    {
        $attributes = [
            'type' => ['value' => 'button', 'dynamic' => false],
            'class' => ['value' => 'btn btn-primary', 'dynamic' => false],
        ];
        
        $node = new ComponentNode('button', $attributes);
        
        $this->assertEquals('component', $node->getType());
        $this->assertEquals('button', $node->tagName);
        $this->assertEquals($attributes, $node->attributes);
        $this->assertNull($node->slot);
    }

    public function test_component_node_with_slot()
    {
        $attributes = [
            'title' => ['value' => 'My Card', 'dynamic' => false],
        ];
        
        $slot = new DocumentNode([
            new TextNode('Slot content'),
            new EchoNode('$variable')
        ]);
        
        $node = new ComponentNode('card', $attributes, $slot);
        
        $this->assertEquals('component', $node->getType());
        $this->assertEquals('card', $node->tagName);
        $this->assertEquals($attributes, $node->attributes);
        $this->assertSame($slot, $node->slot);
    }

    public function test_component_node_dynamic_attributes()
    {
        $attributes = [
            'id' => ['value' => 'static-id', 'dynamic' => false],
            'class' => ['value' => '$dynamicClass', 'dynamic' => true],
            'data-value' => ['value' => '$item->id', 'dynamic' => true],
        ];
        
        $node = new ComponentNode('div', $attributes);
        
        $this->assertEquals($attributes, $node->attributes);
        $this->assertFalse($node->attributes['id']['dynamic']);
        $this->assertTrue($node->attributes['class']['dynamic']);
        $this->assertTrue($node->attributes['data-value']['dynamic']);
    }

    public function test_visitor_pattern_document_node()
    {
        $visitor = $this->createMock(NodeVisitor::class);
        $visitor->expects($this->once())
                ->method('visitDocument')
                ->with($this->isInstanceOf(DocumentNode::class))
                ->willReturn('visited_document');
        
        $node = new DocumentNode();
        $result = $node->accept($visitor);
        
        $this->assertEquals('visited_document', $result);
    }

    public function test_visitor_pattern_text_node()
    {
        $visitor = $this->createMock(NodeVisitor::class);
        $visitor->expects($this->once())
                ->method('visitText')
                ->with($this->isInstanceOf(TextNode::class))
                ->willReturn('visited_text');
        
        $node = new TextNode('content');
        $result = $node->accept($visitor);
        
        $this->assertEquals('visited_text', $result);
    }

    public function test_visitor_pattern_echo_node()
    {
        $visitor = $this->createMock(NodeVisitor::class);
        $visitor->expects($this->once())
                ->method('visitEcho')
                ->with($this->isInstanceOf(EchoNode::class))
                ->willReturn('visited_echo');
        
        $node = new EchoNode('$var');
        $result = $node->accept($visitor);
        
        $this->assertEquals('visited_echo', $result);
    }

    public function test_visitor_pattern_directive_node()
    {
        $visitor = $this->createMock(NodeVisitor::class);
        $visitor->expects($this->once())
                ->method('visitDirective')
                ->with($this->isInstanceOf(DirectiveNode::class))
                ->willReturn('visited_directive');
        
        $node = new DirectiveNode('if', '$condition');
        $result = $node->accept($visitor);
        
        $this->assertEquals('visited_directive', $result);
    }

    public function test_visitor_pattern_component_node()
    {
        $visitor = $this->createMock(TestNodeVisitorWithComponent::class);
        $visitor->expects($this->once())
                ->method('visitComponentNode')
                ->with($this->isInstanceOf(ComponentNode::class))
                ->willReturn('visited_component');
        
        $node = new ComponentNode('button', []);
        $result = $node->accept($visitor);
        
        $this->assertEquals('visited_component', $result);
    }

    public function test_nodes_are_mutable()
    {
        // Test that node properties can be modified
        $textNode = new TextNode('original');
        $textNode->content = 'modified';
        $this->assertEquals('modified', $textNode->content);

        $echoNode = new EchoNode('$original');
        $echoNode->expression = '$modified';
        $echoNode->raw = true;
        $this->assertEquals('$modified', $echoNode->expression);
        $this->assertTrue($echoNode->raw);

        $directiveNode = new DirectiveNode('if', '$original');
        $directiveNode->name = 'unless';
        $directiveNode->expression = '$modified';
        $directiveNode->children = [new TextNode('child')];
        $this->assertEquals('unless', $directiveNode->name);
        $this->assertEquals('$modified', $directiveNode->expression);
        $this->assertCount(1, $directiveNode->children);
    }

    public function test_nested_node_structures()
    {
        // Create a complex nested structure
        $root = new DocumentNode([
            new TextNode('Header'),
            new DirectiveNode('if', '$showContent', [
                new TextNode('Inside if block'),
                new EchoNode('$content'),
                new ComponentNode('alert', [
                    'type' => ['value' => 'info', 'dynamic' => false]
                ], new DocumentNode([
                    new TextNode('Alert content: '),
                    new EchoNode('$message')
                ]))
            ]),
            new TextNode('Footer')
        ]);

        $this->assertCount(3, $root->children);
        $this->assertInstanceOf(TextNode::class, $root->children[0]);
        $this->assertInstanceOf(DirectiveNode::class, $root->children[1]);
        $this->assertInstanceOf(TextNode::class, $root->children[2]);

        $ifDirective = $root->children[1];
        $this->assertCount(3, $ifDirective->children);
        $this->assertInstanceOf(ComponentNode::class, $ifDirective->children[2]);

        $component = $ifDirective->children[2];
        $this->assertInstanceOf(DocumentNode::class, $component->slot);
        $this->assertCount(2, $component->slot->children);
    }

    public function test_node_type_consistency()
    {
        $nodeTests = [
            [new DocumentNode(), 'document'],
            [new TextNode(''), 'text'],
            [new EchoNode(''), 'echo'],
            [new DirectiveNode('test'), 'directive'],
            [new ComponentNode('test', []), 'component']
        ];

        foreach ($nodeTests as [$node, $expectedType]) {
            $this->assertEquals($expectedType, $node->getType());
        }
    }
}

// Extended visitor interface for testing ComponentNode
interface TestNodeVisitorWithComponent extends NodeVisitor
{
    public function visitComponentNode(ComponentNode $node);
}