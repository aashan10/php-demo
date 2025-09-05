<?php
namespace App\Middleware;

use Elementary\Http\Middleware\MiddlewareInterface;
use Elementary\Http\Request;
use Elementary\Http\Response;

class Authenticate implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        return $next($request);
    }
}
