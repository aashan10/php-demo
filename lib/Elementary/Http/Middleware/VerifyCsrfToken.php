<?php

declare(strict_types=1);

namespace Elementary\Http\Middleware;

use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Utils\Csrf;

class VerifyCsrfToken implements MiddlewareInterface
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected array $except = [
        // 'api/*',
    ];

    public function __construct(private Csrf $csrf)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if ($this->isReading($request) || $this->inExceptArray($request) || $this->tokensMatch($request)) {
            return $next($request);
        }

        // Return a "419 Page Expired" response with better user experience
        return new Response(419, 
            '<h1>Session Expired</h1>' .
            '<p>For your security, this form has expired. Please <a href="javascript:window.location.reload()">refresh the page</a> and try again.</p>' .
            '<script>setTimeout(function(){ window.location.reload(); }, 3000);</script>'
        );
    }

    private function isReading(Request $request): bool
    {
        return in_array($request->method(), ['HEAD', 'GET', 'OPTIONS']);
    }

    private function inExceptArray(Request $request): bool
    {
        $requestUri = $request->uri();
        
        // Simple wildcard matching for excluded URIs
        foreach ($this->except as $except) {
            // Handle root path exception
            if ($except === '/' && $requestUri === '/') {
                return true;
            }
            
            // Handle other path exceptions
            if ($except !== '/') {
                $except = trim($except, '/');
                $trimmedUri = trim($requestUri, '/');
                
                // Handle wildcard matching for patterns like 'api/*'
                if (str_ends_with($except, '*')) {
                    $prefix = rtrim($except, '/*');
                    // Handle case where URI matches the wildcard pattern
                    if ($trimmedUri === $prefix || str_starts_with($trimmedUri, $prefix . '/')) {
                        return true;
                    }
                } else {
                    // Exact match or prefix match
                    if ($trimmedUri === $except || str_starts_with($trimmedUri, $except . '/')) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function tokensMatch(Request $request): bool
    {
        $token = $request->post->get('_token') ?: $request->headers->get('x-csrf-token');

        return $this->csrf->validate($token);
    }
}
