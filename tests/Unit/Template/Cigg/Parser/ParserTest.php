<?php

declare(strict_types=1);

namespace Tests\Unit\Template\Cigg\Parser;

use Elementary\Template\Cigg\Parser\Parser;
use Elementary\Template\Cigg\Directives\DirectiveRegistry;
use Elementary\Template\Cigg\Token\Token;
use Elementary\Template\Cigg\Token\TokenType;
use Elementary\Template\Cigg\AST\DocumentNode;
use Elementary\Template\Cigg\AST\TextNode;
use Elementary\Template\Cigg\AST\EchoNode;
use Elementary\Template\Cigg\AST\DirectiveNode;
use Elementary\Template\Cigg\AST\ComponentNode;
use Elementary\Template\Cigg\Directives\DirectiveInterface;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    private DirectiveRegistry $registry;
    private Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new DirectiveRegistry();
        $this->parser = new Parser($this->registry);
        
        // Add some basic block directives for testing
        $this->addTestDirectives();
    }

    private function addTestDirectives(): void
    {
        $ifDirective = $this->createMock(DirectiveInterface::class);
        $ifDirective->method('getName')->willReturn('if');
        $ifDirective->method('isBlock')->willReturn(true);
        $this->registry->register($ifDirective);

        $foreachDirective = $this->createMock(DirectiveInterface::class);
        $foreachDirective->method('getName')->willReturn('foreach');
        $foreachDirective->method('isBlock')->willReturn(true);
        $this->registry->register($foreachDirective);

        $sectionDirective = $this->createMock(DirectiveInterface::class);
        $sectionDirective->method('getName')->willReturn('section');
        $sectionDirective->method('isBlock')->willReturn(true);
        $this->registry->register($sectionDirective);
    }

    public function test_parse_empty_tokens()
    {
        $tokens = [
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);

        $this->assertInstanceOf(DocumentNode::class, $ast);
        $this->assertEmpty($ast->children);
    }

    public function test_parse_plain_text()
    {
        $tokens = [
            new Token(TokenType::T_TEXT, 'Hello, World!'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);

        $this->assertInstanceOf(DocumentNode::class, $ast);
        $this->assertCount(1, $ast->children);
        $this->assertInstanceOf(TextNode::class, $ast->children[0]);
        $this->assertEquals('Hello, World!', $ast->children[0]->content);
    }

    public function test_parse_simple_echo()
    {
        $tokens = [
            new Token(TokenType::T_ECHO_START, '{{'),
            new Token(TokenType::T_EXPRESSION, '$name'),
            new Token(TokenType::T_ECHO_END, '}}'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);

        $this->assertInstanceOf(DocumentNode::class, $ast);
        $this->assertCount(1, $ast->children);
        $this->assertInstanceOf(EchoNode::class, $ast->children[0]);
        $this->assertEquals('$name', $ast->children[0]->expression);
        $this->assertFalse($ast->children[0]->raw);
    }

    public function test_parse_raw_echo()
    {
        $tokens = [
            new Token(TokenType::T_RAW_ECHO_START, '{!!'),
            new Token(TokenType::T_EXPRESSION, '$htmlContent'),
            new Token(TokenType::T_RAW_ECHO_END, '!!}'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);

        $this->assertInstanceOf(DocumentNode::class, $ast);
        $this->assertCount(1, $ast->children);
        $this->assertInstanceOf(EchoNode::class, $ast->children[0]);
        $this->assertEquals('$htmlContent', $ast->children[0]->expression);
        $this->assertTrue($ast->children[0]->raw);
    }

    public function test_parse_simple_directive()
    {
        $tokens = [
            new Token(TokenType::T_DIRECTIVE_START, '@'),
            new Token(TokenType::T_IDENTIFIER, 'csrf'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);

        $this->assertInstanceOf(DocumentNode::class, $ast);
        $this->assertCount(1, $ast->children);
        $this->assertInstanceOf(DirectiveNode::class, $ast->children[0]);
        $this->assertEquals('csrf', $ast->children[0]->name);
        $this->assertEquals('', $ast->children[0]->expression);
        $this->assertEmpty($ast->children[0]->children);
    }

    public function test_parse_directive_with_expression()
    {
        $tokens = [
            new Token(TokenType::T_DIRECTIVE_START, '@'),
            new Token(TokenType::T_IDENTIFIER, 'unless'),
            new Token(TokenType::T_EXPRESSION, '($condition)'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);

        $this->assertInstanceOf(DocumentNode::class, $ast);
        $this->assertCount(1, $ast->children);
        $this->assertInstanceOf(DirectiveNode::class, $ast->children[0]);
        $this->assertEquals('unless', $ast->children[0]->name);
        $this->assertEquals('$condition', $ast->children[0]->expression); // Should strip parentheses
        $this->assertEmpty($ast->children[0]->children);
    }

    public function test_parse_block_directive()
    {
        $tokens = [
            new Token(TokenType::T_DIRECTIVE_START, '@'),
            new Token(TokenType::T_IDENTIFIER, 'if'),
            new Token(TokenType::T_EXPRESSION, '($condition)'),
            new Token(TokenType::T_TEXT, 'Content inside if'),
            new Token(TokenType::T_DIRECTIVE_START, '@'),
            new Token(TokenType::T_IDENTIFIER, 'endif'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);

        $this->assertInstanceOf(DocumentNode::class, $ast);
        $this->assertCount(1, $ast->children);
        
        $ifDirective = $ast->children[0];
        $this->assertInstanceOf(DirectiveNode::class, $ifDirective);
        $this->assertEquals('if', $ifDirective->name);
        $this->assertEquals('$condition', $ifDirective->expression);
        $this->assertCount(2, $ifDirective->children);
        
        $this->assertInstanceOf(TextNode::class, $ifDirective->children[0]);
        $this->assertEquals('Content inside if', $ifDirective->children[0]->content);
        
        $this->assertInstanceOf(DirectiveNode::class, $ifDirective->children[1]);
        $this->assertEquals('endif', $ifDirective->children[1]->name);
    }

    public function test_parse_nested_block_directives()
    {
        $tokens = [
            new Token(TokenType::T_DIRECTIVE_START, '@'),
            new Token(TokenType::T_IDENTIFIER, 'if'),
            new Token(TokenType::T_EXPRESSION, '($condition)'),
            new Token(TokenType::T_TEXT, 'Outer content'),
            new Token(TokenType::T_DIRECTIVE_START, '@'),
            new Token(TokenType::T_IDENTIFIER, 'foreach'),
            new Token(TokenType::T_EXPRESSION, '($items as $item)'),
            new Token(TokenType::T_TEXT, 'Inner content'),
            new Token(TokenType::T_DIRECTIVE_START, '@'),
            new Token(TokenType::T_IDENTIFIER, 'endforeach'),
            new Token(TokenType::T_TEXT, 'More outer content'),
            new Token(TokenType::T_DIRECTIVE_START, '@'),
            new Token(TokenType::T_IDENTIFIER, 'endif'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);

        $this->assertInstanceOf(DocumentNode::class, $ast);
        $this->assertCount(1, $ast->children);
        
        $outerIf = $ast->children[0];
        $this->assertInstanceOf(DirectiveNode::class, $outerIf);
        $this->assertEquals('if', $outerIf->name);
        $this->assertCount(4, $outerIf->children); // text, foreach, text, endif
        
        $foreach = $outerIf->children[1];
        $this->assertInstanceOf(DirectiveNode::class, $foreach);
        $this->assertEquals('foreach', $foreach->name);
        $this->assertEquals('$items as $item', $foreach->expression);
        $this->assertCount(2, $foreach->children); // text, endforeach
    }

    public function test_parse_component_self_closing()
    {
        $tokens = [
            new Token(TokenType::T_COMPONENT_TAG, '<ui-button type="submit" />'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);

        $this->assertInstanceOf(DocumentNode::class, $ast);
        $this->assertCount(1, $ast->children);
        
        $component = $ast->children[0];
        $this->assertInstanceOf(ComponentNode::class, $component);
        $this->assertEquals('button', $component->tagName);
        $this->assertNull($component->slot);
        $this->assertArrayHasKey('type', $component->attributes);
        $this->assertEquals('submit', $component->attributes['type']['value']);
        $this->assertFalse($component->attributes['type']['dynamic']);
    }

    public function test_parse_component_with_slot()
    {
        $tokens = [
            new Token(TokenType::T_COMPONENT_TAG, '<ui-card title="My Card">'),
            new Token(TokenType::T_TEXT, 'Card content'),
            new Token(TokenType::T_ECHO_START, '{{'),
            new Token(TokenType::T_EXPRESSION, '$variable'),
            new Token(TokenType::T_ECHO_END, '}}'),
            new Token(TokenType::T_COMPONENT_TAG, '</ui-card>'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);

        $this->assertInstanceOf(DocumentNode::class, $ast);
        $this->assertCount(1, $ast->children);
        
        $component = $ast->children[0];
        $this->assertInstanceOf(ComponentNode::class, $component);
        $this->assertEquals('card', $component->tagName);
        $this->assertArrayHasKey('title', $component->attributes);
        $this->assertEquals('My Card', $component->attributes['title']['value']);
        
        $this->assertInstanceOf(DocumentNode::class, $component->slot);
        $this->assertCount(2, $component->slot->children);
        $this->assertInstanceOf(TextNode::class, $component->slot->children[0]);
        $this->assertInstanceOf(EchoNode::class, $component->slot->children[1]);
    }

    public function test_parse_component_dynamic_attributes()
    {
        $tokens = [
            new Token(TokenType::T_COMPONENT_TAG, '<ui-button :class="$btnClass" :disabled="$isDisabled" id="static-id">'),
            new Token(TokenType::T_TEXT, 'Button text'),
            new Token(TokenType::T_COMPONENT_TAG, '</ui-button>'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);
        
        $component = $ast->children[0];
        $this->assertInstanceOf(ComponentNode::class, $component);
        
        // Dynamic attributes (prefixed with :)
        $this->assertArrayHasKey('class', $component->attributes);
        $this->assertEquals('$btnClass', $component->attributes['class']['value']);
        $this->assertTrue($component->attributes['class']['dynamic']);
        
        $this->assertArrayHasKey('disabled', $component->attributes);
        $this->assertEquals('$isDisabled', $component->attributes['disabled']['value']);
        $this->assertTrue($component->attributes['disabled']['dynamic']);
        
        // Static attributes
        $this->assertArrayHasKey('id', $component->attributes);
        $this->assertEquals('static-id', $component->attributes['id']['value']);
        $this->assertFalse($component->attributes['id']['dynamic']);
    }

    public function test_parse_mixed_content()
    {
        $tokens = [
            new Token(TokenType::T_TEXT, 'Before'),
            new Token(TokenType::T_ECHO_START, '{{'),
            new Token(TokenType::T_EXPRESSION, '$name'),
            new Token(TokenType::T_ECHO_END, '}}'),
            new Token(TokenType::T_TEXT, 'Middle'),
            new Token(TokenType::T_DIRECTIVE_START, '@'),
            new Token(TokenType::T_IDENTIFIER, 'if'),
            new Token(TokenType::T_EXPRESSION, '($condition)'),
            new Token(TokenType::T_TEXT, 'Inside if'),
            new Token(TokenType::T_DIRECTIVE_START, '@'),
            new Token(TokenType::T_IDENTIFIER, 'endif'),
            new Token(TokenType::T_TEXT, 'After'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);

        $this->assertInstanceOf(DocumentNode::class, $ast);
        $this->assertCount(5, $ast->children);
        
        $this->assertInstanceOf(TextNode::class, $ast->children[0]);
        $this->assertEquals('Before', $ast->children[0]->content);
        
        $this->assertInstanceOf(EchoNode::class, $ast->children[1]);
        $this->assertEquals('$name', $ast->children[1]->expression);
        
        $this->assertInstanceOf(TextNode::class, $ast->children[2]);
        $this->assertEquals('Middle', $ast->children[2]->content);
        
        $this->assertInstanceOf(DirectiveNode::class, $ast->children[3]);
        $this->assertEquals('if', $ast->children[3]->name);
    }

    public function test_parse_ending_directive()
    {
        $tokens = [
            new Token(TokenType::T_DIRECTIVE_START, '@'),
            new Token(TokenType::T_IDENTIFIER, 'endif'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);
        
        $this->assertInstanceOf(DocumentNode::class, $ast);
        $this->assertCount(1, $ast->children);
        
        $directive = $ast->children[0];
        $this->assertInstanceOf(DirectiveNode::class, $directive);
        $this->assertEquals('endif', $directive->name);
        $this->assertEquals('', $directive->expression);
        $this->assertEmpty($directive->children);
    }

    public function test_parse_expression_without_parentheses()
    {
        $tokens = [
            new Token(TokenType::T_DIRECTIVE_START, '@'),
            new Token(TokenType::T_IDENTIFIER, 'include'),
            new Token(TokenType::T_EXPRESSION, '"partial.template"'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);
        
        $directive = $ast->children[0];
        $this->assertInstanceOf(DirectiveNode::class, $directive);
        $this->assertEquals('include', $directive->name);
        $this->assertEquals('"partial.template"', $directive->expression);
    }

    public function test_parse_malformed_component_tag()
    {
        $tokens = [
            new Token(TokenType::T_COMPONENT_TAG, '<invalid-component-tag'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);
        
        $this->assertInstanceOf(DocumentNode::class, $ast);
        $this->assertCount(1, $ast->children);
        
        // Should be treated as text node for malformed tags
        $this->assertInstanceOf(TextNode::class, $ast->children[0]);
        $this->assertEquals('<invalid-component-tag', $ast->children[0]->content);
    }

    /* public function test_parse_closing_component_tag_without_opening() */
    /* { */
    /*     $tokens = [ */
    /*         new Token(TokenType::T_COMPONENT_TAG, '</ui-button>'), */
    /*         new Token(TokenType::T_EOF, '') */
    /*     ]; */
    /**/
    /*     $ast = $this->parser->parse($tokens); */
    /**/
    /*     // Closing tag without opening should be ignored/skipped */
    /*     $this->assertInstanceOf(DocumentNode::class, $ast); */
    /*     $this->assertEmpty($ast->children); */
    /* } */

    public function test_parse_complex_nested_structure()
    {
        $tokens = [
            new Token(TokenType::T_TEXT, 'Start'),
            new Token(TokenType::T_DIRECTIVE_START, '@'),
            new Token(TokenType::T_IDENTIFIER, 'section'),
            new Token(TokenType::T_EXPRESSION, '("content")'),
            new Token(TokenType::T_COMPONENT_TAG, '<ui-card>'),
            new Token(TokenType::T_TEXT, 'Card: '),
            new Token(TokenType::T_ECHO_START, '{{'),
            new Token(TokenType::T_EXPRESSION, '$title'),
            new Token(TokenType::T_ECHO_END, '}}'),
            new Token(TokenType::T_COMPONENT_TAG, '</ui-card>'),
            new Token(TokenType::T_DIRECTIVE_START, '@'),
            new Token(TokenType::T_IDENTIFIER, 'endsection'),
            new Token(TokenType::T_TEXT, 'End'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);
        
        $this->assertInstanceOf(DocumentNode::class, $ast);
        $this->assertCount(3, $ast->children);
        
        // First child: "Start" text
        $this->assertInstanceOf(TextNode::class, $ast->children[0]);
        
        // Second child: section directive
        $section = $ast->children[1];
        $this->assertInstanceOf(DirectiveNode::class, $section);
        $this->assertEquals('section', $section->name);
        $this->assertCount(2, $section->children); // component + endsection
        
        // Component inside section
        $component = $section->children[0];
        $this->assertInstanceOf(ComponentNode::class, $component);
        $this->assertEquals('card', $component->tagName);
        $this->assertInstanceOf(DocumentNode::class, $component->slot);
        $this->assertCount(2, $component->slot->children); // text + echo
        
        // Third child: "End" text
        $this->assertInstanceOf(TextNode::class, $ast->children[2]);
    }

    public function test_parse_unknown_tokens_are_ignored()
    {
        $tokens = [
            new Token(TokenType::T_TEXT, 'Valid text'),
            new Token('UNKNOWN_TOKEN_TYPE', 'unknown content'),
            new Token(TokenType::T_TEXT, 'More valid text'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);
        
        $this->assertInstanceOf(DocumentNode::class, $ast);
        $this->assertCount(2, $ast->children);
        
        $this->assertInstanceOf(TextNode::class, $ast->children[0]);
        $this->assertEquals('Valid text', $ast->children[0]->content);
        
        $this->assertInstanceOf(TextNode::class, $ast->children[1]);
        $this->assertEquals('More valid text', $ast->children[1]->content);
    }

    public function test_parse_preserves_token_order()
    {
        $tokens = [
            new Token(TokenType::T_TEXT, 'A'),
            new Token(TokenType::T_TEXT, 'B'),
            new Token(TokenType::T_TEXT, 'C'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);
        
        $this->assertCount(3, $ast->children);
        $this->assertEquals('A', $ast->children[0]->content);
        $this->assertEquals('B', $ast->children[1]->content);
        $this->assertEquals('C', $ast->children[2]->content);
    }

    public function test_parse_closing_component_tags_without_opening_prevents_infinite_loop()
    {
        $tokens = [
            new Token(TokenType::T_TEXT, 'Before'),
            new Token(TokenType::T_COMPONENT_TAG, '</ui-button>'),
            new Token(TokenType::T_COMPONENT_TAG, '</ui-card>'),
            new Token(TokenType::T_TEXT, 'After'),
            new Token(TokenType::T_EOF, '')
        ];

        $ast = $this->parser->parse($tokens);
        
        // Should skip closing tags and continue parsing without infinite loop
        $this->assertInstanceOf(DocumentNode::class, $ast);
        $this->assertCount(2, $ast->children);
        
        $this->assertInstanceOf(TextNode::class, $ast->children[0]);
        $this->assertEquals('Before', $ast->children[0]->content);
        
        $this->assertInstanceOf(TextNode::class, $ast->children[1]);
        $this->assertEquals('After', $ast->children[1]->content);
    }
}
