<?php

declare(strict_types=1);

namespace App\Controllers;

use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Routing\RouteCollection;
use Elementary\Routing\Router;
use Elementary\Utils\FlashBag;
use Elementary\Template\Cigg\Engine as TemplateEngine;

abstract class AbstractController
{

    public function __construct(
        protected Request $request,
        protected FlashBag $flashBag,
        protected TemplateEngine $engine
    ) {
    }

    /** @param array<string, mixed> $args */
    protected function render(string $templateName, array $args = []): Response
    {
        $args['auth'] = (object)[
            'check' => isset($this->request->user),
            'user' => $this->request->user ?? null,
        ];
        $response = $this->engine->render($templateName, $args);

        return new Response(200, $response);
    }

    protected function redirect(string $url): Response
    {
        return new Response(302, '', ['Location' => $url]);
    }

    protected function addFlash(string $type, string $message): void
    {
        $this->flashBag->add($type, $message);
    }

    /** @param array<string, mixed> $data */
    protected function json(array $data, int $status = 200): Response
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        return new Response($status, $json, ['Content-Type' => 'application/json']);
    }

    protected function redirectToRoute(string $namedRoute, array $params = []): Response 
    {
        $route = RouteCollection::getInstance()->getByName($namedRoute);
        if (!$route) {
            throw new \RuntimeException("Route with name '{$namedRoute}' not found.");
        }
        $url = Router::url($route, $params);

        return $this->redirect($url);
    }
}
