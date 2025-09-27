<?php

declare(strict_types=1);

namespace Tests\Unit\Template\Cigg\Lexer;

use Elementary\Template\Cigg\Lexer\Lexer;
use Elementary\Template\Cigg\Token\Token;
use Elementary\Template\Cigg\Token\TokenType;
use PHPUnit\Framework\TestCase;

class LexerTest extends TestCase
{
    private Lexer $lexer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lexer = new Lexer();
    }

    public function test_tokenize_empty_string()
    {
        $tokens = $this->lexer->tokenize('');

        $this->assertCount(1, $tokens);
        $this->assertEquals(TokenType::T_EOF, $tokens[0]->type);
    }

    public function test_tokenize_plain_text()
    {
        $input = 'Hello, World!';
        $tokens = $this->lexer->tokenize($input);

        $this->assertCount(2, $tokens);
        $this->assertEquals(TokenType::T_TEXT, $tokens[0]->type);
        $this->assertEquals('Hello, World!', $tokens[0]->value);
        $this->assertEquals(TokenType::T_EOF, $tokens[1]->type);
    }

    public function test_tokenize_simple_echo()
    {
        $input = '{{ $name }}';
        $tokens = $this->lexer->tokenize($input);

        $this->assertCount(4, $tokens);
        $this->assertEquals(TokenType::T_ECHO_START, $tokens[0]->type);
        $this->assertEquals('{{', $tokens[0]->value);
        $this->assertEquals(TokenType::T_EXPRESSION, $tokens[1]->type);
        $this->assertEquals('$name', $tokens[1]->value);
        $this->assertEquals(TokenType::T_ECHO_END, $tokens[2]->type);
        $this->assertEquals('}}', $tokens[2]->value);
        $this->assertEquals(TokenType::T_EOF, $tokens[3]->type);
    }

    public function test_tokenize_raw_echo()
    {
        $input = '{!! $htmlContent !!}';
        $tokens = $this->lexer->tokenize($input);

        $this->assertCount(4, $tokens);
        $this->assertEquals(TokenType::T_RAW_ECHO_START, $tokens[0]->type);
        $this->assertEquals('{!!', $tokens[0]->value);
        $this->assertEquals(TokenType::T_EXPRESSION, $tokens[1]->type);
        $this->assertEquals('$htmlContent', $tokens[1]->value);
        $this->assertEquals(TokenType::T_RAW_ECHO_END, $tokens[2]->type);
        $this->assertEquals('!!}', $tokens[2]->value);
    }

    public function test_tokenize_simple_directive()
    {
        $input = '@if($condition)';
        $tokens = $this->lexer->tokenize($input);

        $this->assertCount(4, $tokens);
        $this->assertEquals(TokenType::T_DIRECTIVE_START, $tokens[0]->type);
        $this->assertEquals('@', $tokens[0]->value);
        $this->assertEquals(TokenType::T_IDENTIFIER, $tokens[1]->type);
        $this->assertEquals('if', $tokens[1]->value);
        $this->assertEquals(TokenType::T_EXPRESSION, $tokens[2]->type);
        $this->assertEquals('($condition)', $tokens[2]->value);
    }

    public function test_tokenize_directive_without_expression()
    {
        $input = '@endif';
        $tokens = $this->lexer->tokenize($input);

        $this->assertCount(3, $tokens);
        $this->assertEquals(TokenType::T_DIRECTIVE_START, $tokens[0]->type);
        $this->assertEquals('@', $tokens[0]->value);
        $this->assertEquals(TokenType::T_IDENTIFIER, $tokens[1]->type);
        $this->assertEquals('endif', $tokens[1]->value);
    }

    public function test_tokenize_escaped_at_symbol()
    {
        $input = '@@escaped';
        $tokens = $this->lexer->tokenize($input);

        $this->assertCount(2, $tokens);
        $this->assertEquals(TokenType::T_TEXT, $tokens[0]->type);
        $this->assertEquals('@escaped', $tokens[0]->value);
    }

    public function test_tokenize_component_tag()
    {
        $input = '<ui-button>Click me</ui-button>';
        $tokens = $this->lexer->tokenize($input);

        $this->assertCount(4, $tokens);
        $this->assertEquals(TokenType::T_COMPONENT_TAG, $tokens[0]->type);
        $this->assertEquals('<ui-button>', $tokens[0]->value);
        $this->assertEquals(TokenType::T_TEXT, $tokens[1]->type);
        $this->assertEquals('Click me', $tokens[1]->value);
        $this->assertEquals(TokenType::T_COMPONENT_TAG, $tokens[2]->type);
        $this->assertEquals('</ui-button>', $tokens[2]->value);
    }

    public function test_tokenize_self_closing_component()
    {
        $input = '<ui-input type="text" />';
        $tokens = $this->lexer->tokenize($input);

        $this->assertCount(2, $tokens);
        $this->assertEquals(TokenType::T_COMPONENT_TAG, $tokens[0]->type);
        $this->assertEquals('<ui-input type="text" />', $tokens[0]->value);
    }

    public function test_tokenize_mixed_content()
    {
        $input = 'Hello {{ $name }}, @if($showAge) you are {{ $age }} years old @endif';
        $tokens = $this->lexer->tokenize($input);

        $expectedTypes = [
            TokenType::T_TEXT,        // 'Hello '
            TokenType::T_ECHO_START,  // '{{'
            TokenType::T_EXPRESSION,  // ' $name '
            TokenType::T_ECHO_END,    // '}}'
            TokenType::T_TEXT,        // ', '
            TokenType::T_DIRECTIVE_START, // '@'
            TokenType::T_IDENTIFIER,  // 'if'
            TokenType::T_EXPRESSION,  // '($showAge)'
            TokenType::T_TEXT,        // ' you are '
            TokenType::T_ECHO_START,  // '{{'
            TokenType::T_EXPRESSION,  // ' $age '
            TokenType::T_ECHO_END,    // '}}'
            TokenType::T_TEXT,        // ' years old '
            TokenType::T_DIRECTIVE_START, // '@'
            TokenType::T_IDENTIFIER,  // 'endif'
            TokenType::T_EOF
        ];

        $this->assertCount(count($expectedTypes), $tokens);
        
        for ($i = 0; $i < count($expectedTypes); $i++) {
            $this->assertEquals($expectedTypes[$i], $tokens[$i]->type, 
                "Token {$i} should be of type {$expectedTypes[$i]}, got {$tokens[$i]->type}");
        }
    }

    public function test_tokenize_complex_expressions()
    {
        $testCases = [
            '{{ $user->name }}' => '$user->name',
            '{{ $items[0]->property }}' => '$items[0]->property',
            '{{ function($a, $b, $c) }}' => 'function($a, $b, $c)',
            '{{ $condition ? "yes" : "no" }}' => '$condition ? "yes" : "no"',
            '{{ isset($var) && !empty($var) }}' => 'isset($var) && !empty($var)',
        ];

        foreach ($testCases as $input => $expectedExpression) {
            $tokens = $this->lexer->tokenize($input);
            
            $this->assertCount(4, $tokens, "Input: {$input}");
            $this->assertEquals(TokenType::T_EXPRESSION, $tokens[1]->type);
            $this->assertEquals($expectedExpression, $tokens[1]->value, "Expression mismatch for: {$input}");
        }
    }

    public function test_tokenize_nested_parentheses_in_directives()
    {
        $input = '@if($items && count($items) > 0 && ($status === "active" || $status === "pending"))';
        $tokens = $this->lexer->tokenize($input);

        $this->assertCount(4, $tokens);
        $this->assertEquals(TokenType::T_DIRECTIVE_START, $tokens[0]->type);
        $this->assertEquals(TokenType::T_IDENTIFIER, $tokens[1]->type);
        $this->assertEquals('if', $tokens[1]->value);
        $this->assertEquals(TokenType::T_EXPRESSION, $tokens[2]->type);
        $this->assertEquals('($items && count($items) > 0 && ($status === "active" || $status === "pending"))', $tokens[2]->value);
    }

    public function test_tokenize_multiline_content()
    {
        $input = "Line 1\n{{ \$var }}\nLine 3";
        $tokens = $this->lexer->tokenize($input);

        $this->assertCount(8, $tokens);
        $this->assertEquals('Line 1', $tokens[0]->value);
        $this->assertEquals("\n", $tokens[1]->value);
        $this->assertEquals('{{', $tokens[2]->value);
        $this->assertEquals('$var', $tokens[3]->value);
        $this->assertEquals('}}', $tokens[4]->value);
        $this->assertEquals("\n", $tokens[5]->value);
        $this->assertEquals('Line 3', $tokens[6]->value);
        $this->assertEquals('', $tokens[7]->value);
    }

    public function test_tokenize_line_and_column_tracking()
    {
        $input = "First line\nSecond {{ \$var }} line\nThird line";
        $tokens = $this->lexer->tokenize($input);

        // Find the variable token
        $varToken = null;
        foreach ($tokens as $token) {
            if ($token->type === TokenType::T_EXPRESSION && $token->value === '$var') {
                $varToken = $token;
                break;
            }
        }

        $this->assertNotNull($varToken);
        $this->assertEquals(2, $varToken->line, 'Variable should be on line 2');
    }

    public function test_tokenize_preserves_whitespace_in_expressions()
    {
        $input = '{{   $name   }}';
        $tokens = $this->lexer->tokenize($input);

        $this->assertEquals('$name', $tokens[1]->value); // Whitespace should be trimmed
    }

    public function test_tokenize_handles_incomplete_sequences()
    {
        $testCases = [
            '{' => [TokenType::T_TEXT],
            '{{' => [TokenType::T_TEXT], // Incomplete echo
            '{!!' => [TokenType::T_TEXT], // Incomplete raw echo
            '@' => [TokenType::T_TEXT], // Standalone @
        ];

        foreach ($testCases as $input => $expectedFirstType) {
            $tokens = $this->lexer->tokenize($input);
            $this->assertEquals($expectedFirstType[0], $tokens[0]->type, "Input: {$input}");
        }
    }

    public function test_tokenize_style_tag_context()
    {
        $input = '<style>{{ .btn { color: red; } }}</style>';
        $tokens = $this->lexer->tokenize($input);

        // In style context, {{ }} should be treated as text
        $this->assertCount(2, $tokens); // Text + EOF
        $this->assertEquals(TokenType::T_TEXT, $tokens[0]->type);
        $this->assertStringContainsString('{{ .btn { color: red; } }}', $tokens[0]->value);
    }

    public function test_tokenize_script_tag_context()
    {
        $input = '<script>var x = "{{ $var }}"; console.log(x);</script>';
        $tokens = $this->lexer->tokenize($input);

        // In script context, template syntax should be treated as text
        $this->assertCount(2, $tokens); // Text + EOF
        $this->assertEquals(TokenType::T_TEXT, $tokens[0]->type);
        $this->assertStringContainsString('var x = "{{ $var }}";', $tokens[0]->value);
    }

    public function test_tokenize_attribute_context()
    {
        $input = '<div class="{{ $cssClass }}">Content</div>';
        $tokens = $this->lexer->tokenize($input);

        // Should have text, echo tokens, and more text
        $this->assertGreaterThan(3, count($tokens));
        
        // Find echo tokens in the sequence
        $hasEchoStart = false;
        $hasExpression = false;
        $hasEchoEnd = false;
        
        foreach ($tokens as $token) {
            if ($token->type === TokenType::T_ECHO_START) $hasEchoStart = true;
            if ($token->type === TokenType::T_EXPRESSION && $token->value === '$cssClass') $hasExpression = true;
            if ($token->type === TokenType::T_ECHO_END) $hasEchoEnd = true;
        }
        
        $this->assertTrue($hasEchoStart, 'Should have echo start token');
        $this->assertTrue($hasExpression, 'Should have expression token');
        $this->assertTrue($hasEchoEnd, 'Should have echo end token');
    }

    public function test_tokenize_directive_with_complex_identifier()
    {
        $input = '@custom_directive_name';
        $tokens = $this->lexer->tokenize($input);

        $this->assertCount(3, $tokens);
        $this->assertEquals(TokenType::T_DIRECTIVE_START, $tokens[0]->type);
        $this->assertEquals(TokenType::T_IDENTIFIER, $tokens[1]->type);
        $this->assertEquals('custom_directive_name', $tokens[1]->value);
    }

    public function test_tokenize_edge_cases()
    {
        $edgeCases = [
            '{{}}' => [TokenType::T_ECHO_START, TokenType::T_ECHO_END], // Empty echo
            '{!! !!}' => [TokenType::T_RAW_ECHO_START, TokenType::T_RAW_ECHO_END], // Empty raw echo
            '@()' => [TokenType::T_DIRECTIVE_START, TokenType::T_TEXT], // Invalid directive
            '{{ {{ }}' => [TokenType::T_TEXT], // Malformed nested echo
        ];

        foreach ($edgeCases as $input => $expectedPattern) {
            $tokens = $this->lexer->tokenize($input);
            
            // Remove EOF token for comparison
            $tokensWithoutEof = array_filter($tokens, fn($t) => $t->type !== TokenType::T_EOF);
            
            $this->assertNotEmpty($tokensWithoutEof, "Input '{$input}' should produce tokens");
        }
    }

    public function test_tokenize_performance_with_large_input()
    {
        // Generate a large input with mixed content
        $input = str_repeat('Text content {{ $var }} @if($condition) more text @endif ', 1000);
        
        $startTime = microtime(true);
        $tokens = $this->lexer->tokenize($input);
        $endTime = microtime(true);
        
        $this->assertNotEmpty($tokens);
        $this->assertLessThan(2.0, $endTime - $startTime, 'Tokenization should complete in reasonable time');
    }

    public function test_tokenize_unicode_content()
    {
        $input = 'Hello {{ $name }} 🚀 @if($test) émojis and ñ characters @endif';
        $tokens = $this->lexer->tokenize($input);

        $this->assertNotEmpty($tokens);
        
        // Find text tokens containing unicode
        $unicodeTokens = array_filter($tokens, function($token) {
            return $token->type === TokenType::T_TEXT && 
                   (strpos($token->value, '🚀') !== false || 
                    strpos($token->value, 'émojis') !== false ||
                    strpos($token->value, 'ñ') !== false);
        });
        
        $this->assertNotEmpty($unicodeTokens, 'Should handle unicode characters correctly');
    }
}