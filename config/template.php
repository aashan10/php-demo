<?php
return [
    'paths' => [
        'views' => TEMPLATE_PATH,
        'cache' => CACHE_PATH . '/templates',
    ],
    'directives' => [
        \Elementary\Template\Cigg\Directives\BuiltIn\CsrfDirective::class,
        \Elementary\Template\Cigg\Directives\BuiltIn\JsonDirective::class,
        \Elementary\Template\Cigg\Directives\BuiltIn\IfDirective::class,
        \Elementary\Template\Cigg\Directives\BuiltIn\ForeachDirective::class,
    ],
];
