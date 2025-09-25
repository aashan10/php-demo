<?php

declare(strict_types=1);

// Polyfill for getallheaders() function which is not available in CLI
if (!function_exists('getallheaders')) {
    function getallheaders(): array {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($name, 5));
                $headerName = ucwords(strtolower($headerName), '-');
                $headers[$headerName] = $value;
            }
        }
        return $headers;
    }
}