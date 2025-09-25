<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg\Token;

class TokenType {
    const T_TEXT = 'TEXT';
    const T_ECHO_START = 'ECHO_START';          // {{
    const T_ECHO_END = 'ECHO_END';              // }}
    const T_RAW_ECHO_START = 'RAW_ECHO_START';  // {!!
    const T_RAW_ECHO_END = 'RAW_ECHO_END';      // !!}
    const T_DIRECTIVE_START = 'DIRECTIVE_START'; // @
    const T_IDENTIFIER = 'IDENTIFIER';           // if, foreach, etc.
    const T_LPAREN = 'LPAREN';                  // (
    const T_RPAREN = 'RPAREN';                  // )
    const T_EXPRESSION = 'EXPRESSION';          // PHP expression
    const T_WHITESPACE = 'WHITESPACE';
    const T_COMPONENT_TAG = 'COMPONENT_TAG';
    const T_EOF = 'EOF';
}
