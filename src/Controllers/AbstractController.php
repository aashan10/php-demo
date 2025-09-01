<?php

declare(strict_types=1);

namespace App\Controllers;

use Elementary\Http\Response;
use Elementary\Utils\FlashBag;
use Elementary\DI\Container;

abstract class AbstractController {

    protected FlashBag $flashBag;

    public function __construct(FlashBag $flashBag)
    {
        $this->flashBag = $flashBag;
    }
    
    protected function render(string $templateName, array $args = []): Response
    {
        $args['flash_errors'] = $this->flashBag->get('errors', []);
        
        return (new Response())->setTemplate(TEMPLATE_PATH . $templateName, $args);
    }
}