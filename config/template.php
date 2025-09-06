<?php
return [
    'paths' => [
        'views' => TEMPLATE_PATH,
        'cache' => CACHE_PATH . '/templates',
    ],
    'directives' => [
        \Elementary\Template\Cigg\Directives\BuiltIn\PhpDirective::class,
        \Elementary\Template\Cigg\Directives\BuiltIn\IncludeDirective::class,
        \Elementary\Template\Cigg\Directives\BuiltIn\CsrfDirective::class,
        \Elementary\Template\Cigg\Directives\BuiltIn\JsonDirective::class,
        \Elementary\Template\Cigg\Directives\BuiltIn\IfDirective::class,
        \Elementary\Template\Cigg\Directives\BuiltIn\ForeachDirective::class,
        \Elementary\Template\Cigg\Directives\BuiltIn\ExtendsDirective::class,
        \Elementary\Template\Cigg\Directives\BuiltIn\SectionDirective::class,
        \Elementary\Template\Cigg\Directives\BuiltIn\YieldDirective::class,
    ],
];
