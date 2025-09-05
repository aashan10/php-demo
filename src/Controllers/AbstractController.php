<?php

declare(strict_types=1);

namespace App\Controllers;

use Elementary\Http\Response;
use Elementary\Utils\FlashBag;
use Elementary\Template\Cigg\Engine as TemplateEngine;

abstract class AbstractController
{

    public function __construct(protected FlashBag $flashBag, protected TemplateEngine $engine)
    {
    }

    protected function render(string $templateName, array $args = []): Response
    {
        $response = $this->engine->render($templateName, $args);

        return new Response(200, $response);
    }
}
